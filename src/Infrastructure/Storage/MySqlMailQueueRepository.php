<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\MailQueueRepositoryInterface;
use App\Contracts\System\JsonHelperInterface;
use App\Core\Entity\MailJob;
use Exception;
use PDO;
use Throwable;

final readonly class MySqlMailQueueRepository implements MailQueueRepositoryInterface
{
    use DynamicSqlTrait;
    use EntityHydratorTrait;

    public function __construct(
        private PDO $pdo,
        private ConfigInterface $config,
        private JsonHelperInterface $jsonHelper,
    ) {
    }

    public function enqueue(MailJob $job): void
    {
        $table = $this->config->get('storage_config')['mail_queue']['table'];
        $data = $this->extractEntity($job, [
            'template' => $job->template->value,
        ]);
        $this->executeUpsert($table, $data, ['id']);
    }

    public function processBatch(int $limit, callable $processor, array $allowedTemplates = []): int
    {
        $sentCount = 0;
        $stmtLock = $this->pdo->query("SELECT GET_LOCK('kga_mail_queue', 2)");
        $lockAcquired = $stmtLock !== false ? $stmtLock->fetchColumn() : false;

        if (\in_array($lockAcquired, [false, 0, '0'], true)) {
            return 0;
        }

        $templateFilterSql = '';
        $params = [];

        if ($allowedTemplates !== []) {
            $inQuery = \implode(',', \array_fill(0, \count($allowedTemplates), '?'));
            $templateFilterSql = " AND template IN ($inQuery)";
            $params = $allowedTemplates;
        }

        try {
            $table = $this->config->get('storage_config')['mail_queue']['table'];

            $updateSql = 'UPDATE `' . $table . '` SET attempts = attempts + 100 ' .
                "WHERE attempts < 3 {$templateFilterSql} " .
                "ORDER BY priority DESC, created_at ASC LIMIT {$limit}";

            $stmtUpdate = $this->pdo->prepare($updateSql);
            $stmtUpdate->execute($params);

            $selectSql = 'SELECT * FROM `' . $table . '` ' .
                "WHERE attempts >= 100 {$templateFilterSql} " .
                'ORDER BY priority DESC, created_at ASC';

            $stmtSelect = $this->pdo->prepare($selectSql);
            $stmtSelect->execute($params);

            $items = $stmtSelect->fetchAll(PDO::FETCH_ASSOC);
            if (!\is_array($items)) {
                return 0;
            }

            foreach ($items as $item) {
                if (!\is_array($item)) {
                    continue;
                }
                /** @var array<string, mixed> $validItem */
                $validItem = $item;
                if (!$this->processSingleMailJob($validItem, $processor)) {
                    continue;
                }
                ++$sentCount;
            }
        } finally {
            $this->pdo->query("SELECT RELEASE_LOCK('kga_mail_queue')");
        }

        return $sentCount;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function processSingleMailJob(array $item, callable $processor): bool
    {
        $recipient = \is_string($item['recipient'] ?? null) ? $item['recipient'] : '';
        $replyTo = \is_string($item['reply_to'] ?? null) ? $item['reply_to'] : null;
        $subject = \is_string($item['subject'] ?? null) ? $item['subject'] : '';
        $template = \is_string($item['template'] ?? null) ? $item['template'] : '';
        $dataStr = \is_string($item['data'] ?? null) ? $item['data'] : '{}';

        $rawId = $item['id'] ?? '';
        $idStr = \is_string($rawId) ? $rawId : (\is_numeric($rawId) ? (string) $rawId : '');

        $rawAttempts = $item['attempts'] ?? 0;
        $attempts = \is_numeric($rawAttempts) ? (int) $rawAttempts : 0;

        try {
            $processor($recipient, $subject, $template, $this->jsonHelper->decode($dataStr), $replyTo);
            $this->delete($idStr);

            return true;
        } catch (Throwable $t) {
            $rootPath = \rtrim((string) $this->config->get('root_path', ''), '/\\');
            $logPath = $rootPath . '/logs/mail_queue_errors.log';
            $logMsg = '[' . \date('d-M-Y H:i:s e') . "] MailQueue Error [ID {$idStr}]: " . $t->getMessage() . "\n";
            @\file_put_contents($logPath, $logMsg, \FILE_APPEND | \LOCK_EX);

            $origAttempts = $attempts - 100 + 1;

            if ($origAttempts >= 3) {
                $this->delete($idStr);

                return false;
            }

            $table = $this->config->get('storage_config')['mail_queue']['table'];
            $this->pdo->prepare('UPDATE `' . $table . '` SET attempts = ? WHERE id = ?')
                ->execute([$origAttempts, $idStr]);

            return false;
        }
    }

    public function import(array $data): void
    {
        $table = $this->config->get('storage_config')['mail_queue']['table'];

        $this->pdo->beginTransaction();

        try {
            $sql = null;
            $stmt = null;

            foreach ($data as $id => $item) {
                $payload = $item['data'] ?? [];
                $mapped = [
                    'id' => $id,
                    'recipient' => $item['recipient'] ?? '',
                    'subject' => $item['subject'] ?? '',
                    'template' => $item['template'] ?? '',
                    'data' => \is_array($payload) ? \json_encode($payload, \JSON_UNESCAPED_UNICODE) : $payload,
                    'attempts' => (int) ($item['attempts'] ?? 0),
                    'priority' => (int) ($item['priority'] ?? 10),
                    'created_at' => $item['created_at'] ?? '',
                ];

                if ($sql === null) {
                    $sql = $this->buildReplaceSql($table, $mapped);
                    $stmt = $this->pdo->prepare($sql);
                }

                $stmt->execute($mapped);
            }

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }

    public function findAllQueue(): array
    {
        $table = $this->config->get('storage_config')['mail_queue']['table'];
        $stmt = $this->pdo->query('SELECT * FROM `' . $table . '` ORDER BY created_at DESC');

        if ($stmt === false) {
            return [];
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!\is_array($rows)) {
            return [];
        }

        /** @var array<int, array<string, mixed>> $validRows */
        $validRows = [];
        foreach ($rows as $r) {
            if (!\is_array($r)) {
                continue;
            }
            /** @var array<string, mixed> $validR */
            $validR = $r;
            $validRows[] = $validR;
        }

        return $validRows;
    }

    public function findById(string $id): ?array
    {
        $table = $this->config->get('storage_config')['mail_queue']['table'];
        $stmt = $this->pdo->prepare('SELECT * FROM `' . $table . '` WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!\is_array($row)) {
            return null;
        }

        /** @var array<string, mixed> $validRow */
        $validRow = $row;

        return $validRow;
    }

    public function delete(string $id): void
    {
        $table = $this->config->get('storage_config')['mail_queue']['table'];
        $this->pdo->prepare('DELETE FROM `' . $table . '` WHERE id = ?')->execute([$id]);
    }
}
