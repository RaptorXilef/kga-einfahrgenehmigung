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

        $orderBy = "
            CASE template
                WHEN 'magic_link' THEN 0
                WHEN 'verify_email' THEN 0
                WHEN 'permit_a4_document' THEN 1
                WHEN 'payment_request' THEN 2
                WHEN 'permit_cancelled' THEN 3
                WHEN 'board_notification' THEN 5
                WHEN 'payment_reminder' THEN 9
                ELSE 7
            END ASC,
            created_at ASC
        ";

        $lockAcquired = $this->pdo->query("SELECT GET_LOCK('kga_mail_queue', 2)")->fetchColumn();

        if (!$lockAcquired) {
            return 0;
        }

        try {
            $table = $this->config->get('storage_config')['mail_queue']['table'];

            $this->pdo->exec("UPDATE `{$table}` SET attempts = attempts + 100 WHERE attempts < 3 ORDER BY {$orderBy} LIMIT {$limit}");
            $items = $this->pdo->query("SELECT * FROM `{$table}` WHERE attempts >= 100 ORDER BY {$orderBy}")->fetchAll(PDO::FETCH_ASSOC);

            foreach ($items as $item) {
                try {
                    $processor(
                        $item['recipient'],
                        $item['subject'],
                        $item['template'],
                        $this->jsonHelper->decode((string) $item['data']),
                    );
                    $this->pdo->prepare("DELETE FROM `{$table}` WHERE id = ?")->execute([$item['id']]);
                    ++$sentCount;
                } catch (Throwable $t) {
                    $logMsg = '[' . \date('d-M-Y H:i:s e') . "] MailQueue Error [ID {$item['id']}]: " . $t->getMessage() . "\n";
                    $logPath = $this->config->getStoragePath('logs/mail_queue_errors.log');
                    @\file_put_contents($logPath, $logMsg, \FILE_APPEND | \LOCK_EX);

                    $origAttempts = $item['attempts'] - 100 + 1;
                    if ($origAttempts >= 3) {
                        $this->pdo->prepare("DELETE FROM `{$table}` WHERE id = ?")->execute([$item['id']]);
                    } else {
                        $this->pdo->prepare("UPDATE `{$table}` SET attempts = ? WHERE id = ?")->execute([$origAttempts, $item['id']]);
                    }
                }
            }
        } finally {
            $this->pdo->query("SELECT RELEASE_LOCK('kga_mail_queue')");
        }

        return $sentCount;
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
}
