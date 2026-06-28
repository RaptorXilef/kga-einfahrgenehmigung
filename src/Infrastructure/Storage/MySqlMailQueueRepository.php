<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\MailQueueRepositoryInterface;
use App\Core\Entity\MailJob;

/**
 * TODO
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class MySqlMailQueueRepository implements MailQueueRepositoryInterface
{
    use DynamicSqlTrait;

    public function __construct(
        private \PDO $pdo,
        private ConfigInterface $config,
    ) {
    }

    public function enqueue(MailJob $job): void
    {
        $table = $this->config->get('storage_config')['mail_queue']['table'];
        $data  = [
            'recipient'  => $job->recipient,
            'subject'    => $job->subject,
            'template'   => $job->template,
            'data'       => \json_encode($job->data, \JSON_UNESCAPED_UNICODE),
            'created_at' => $job->createdAt->format('Y-m-d H:i:s'),
        ];

        $sql = $this->buildReplaceSql($table, $data);
        $this->pdo->prepare($sql)->execute($data);
    }

    public function processBatch(int $limit, callable $processor): int
    {
        $table     = $this->config->get('storage_config')['mail_queue']['table'];
        $sentCount = 0;

        // #Email #Priorität #Query #Warteschlange
        // PRIORISIERUNG: 0 = Höchste, 9 = Niedrigste
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

        // 1. Zeilen blockieren (Locking durch Attempts-Erhöhung)
        $this->pdo->exec("UPDATE `{$table}` SET attempts = attempts + 100 WHERE attempts < 3 ORDER BY {$orderBy} LIMIT {$limit}");

        // 2. Die blockierten Zeilen abrufen
        $items = $this->pdo->query("SELECT * FROM `{$table}` WHERE attempts >= 100 ORDER BY {$orderBy}")->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($items as $item) {
            try {
                $processor($item['recipient'], $item['subject'], $item['template'], JsonHelper::decode((string) $item['data']));
                // Nach Erfolg löschen
                $this->pdo->prepare("DELETE FROM `{$table}` WHERE id = ?")->execute([$item['id']]);
                ++$sentCount;
            } catch (\Throwable $t) {
                \error_log("MailQueue Error [ID {$item['id']}]: " . $t->getMessage());
                // Entsperren und Fehler zählen
                $origAttempts = $item['attempts'] - 100 + 1;
                if ($origAttempts >= 3) {
                    $this->pdo->prepare("DELETE FROM `{$table}` WHERE id = ?")->execute([$item['id']]);
                } else {
                    $this->pdo->prepare("UPDATE `{$table}` SET attempts = ? WHERE id = ?")->execute([$origAttempts, $item['id']]);
                }
            }
        }

        return $sentCount;
    }

    public function import(array $data): void
    {
        $table = $this->config->get('storage_config')['mail_queue']['table'];
        $this->pdo->beginTransaction();

        try {
            $sql  = null;
            $stmt = null;

            foreach ($data as $id => $item) {
                $payload = $item['data'] ?? [];
                $mapped  = [
                    'id'         => $id,
                    'recipient'  => $item['recipient'] ?? '',
                    'subject'    => $item['subject'] ?? '',
                    'template'   => $item['template'] ?? '',
                    'data'       => \is_array($payload) ? \json_encode($payload, \JSON_UNESCAPED_UNICODE) : $payload,
                    'attempts'   => (int) ($item['attempts'] ?? 0),
                    'created_at' => $item['created_at'] ?? '',
                ];

                if ($sql === null) {
                    $sql  = $this->buildReplaceSql($table, $mapped);
                    $stmt = $this->pdo->prepare($sql);
                }
                $stmt->execute($mapped);
            }
            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }
}
