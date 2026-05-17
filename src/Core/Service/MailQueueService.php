<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

// Path: src/Core/Service/MailQueueService.php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Mail\MailServiceInterface;

final readonly class MailQueueService implements MailServiceInterface
{
    public function __construct(
        private ConfigInterface $config,
        private ?\PDO $pdo,
        private MailServiceInterface $realMailService, // Der echte SMTP-Service
    ) {
    }

    public function sendTemplate(string $recipient, string $subject, string $template, array $data): bool|string
    {
        $cfg     = $this->config->get('storage_config')['mail_queue'];
        $payload = \json_encode($data);

        if ($cfg['type'] === 'mysql' && $this->pdo) {
            $stmt = $this->pdo->prepare('INSERT INTO mail_queue (recipient, subject, template, data, created_at) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$recipient, $subject, $template, $payload, \date('Y-m-d H:i:s')]);
        } else {
            $path    = $this->config->get('root_path') . $this->config->get('storage_path_prefix') . $cfg['file'];
            $queue   = \file_exists($path) ? \json_decode((string) \file_get_contents($path), true) : [];
            $queue[] = [
                'recipient'  => $recipient,
                'subject'    => $subject,
                'template'   => $template,
                'data'       => $data, // Hier direkt das Array speichern
                'attempts'   => 0,
                'created_at' => \date('Y-m-d H:i:s'),
            ];
            \file_put_contents($path, \json_encode($queue, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
        }

        return true; // "Erfolg", da in Queue gespeichert
    }

    public function processQueue(int $limit = 5): int
    {
        $cfg       = $this->config->get('storage_config')['mail_queue'];
        $sentCount = 0;

        // --- A. MYSQL LOGIK ---
        if ($cfg['type'] === 'mysql' && $this->pdo) {
            // Markieren (Locking)
            $this->pdo->prepare("UPDATE mail_queue SET attempts = attempts + 100 WHERE attempts < 3 LIMIT $limit")->execute();

            // Jetzt holen wir uns die markierten Mails
            $stmt  = $this->pdo->query('SELECT * FROM mail_queue WHERE attempts >= 100 ORDER BY created_at ASC');
            $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($items as $item) {
                try {
                    $result = $this->realMailService->sendTemplate(
                        $item['recipient'],
                        $item['subject'],
                        $item['template'],
                        \json_decode($item['data'], true),
                    );
                    if ($result === true) {
                        $this->pdo->prepare('DELETE FROM mail_queue WHERE id = ?')->execute([$item['id']]);
                        ++$sentCount;
                    } else {
                        throw new \Exception((string) $result);
                    }
                } catch (\Throwable $t) {
                    $origAttempts = ($item['attempts'] - 100) + 1;
                    $this->pdo->prepare('UPDATE mail_queue SET attempts = ?, last_error = ? WHERE id = ?')
                        ->execute([$origAttempts, $t->getMessage(), $item['id']]);
                }
            }
        }
        // --- B. JSON LOGIK ---
        else {
            $path = $this->config->get('root_path') . $this->config->get('storage_path_prefix') . $cfg['file'];
            if (! \file_exists($path)) {
                return 0;
            }

            for ($i = 0; $i < $limit; ++$i) {
                // Wir müssen die Datei jedes Mal neu lesen, falls parallel Prozesse laufen
                $queue = \json_decode((string) \file_get_contents($path), true) ?? [];
                if (empty($queue)) {
                    break;
                }

                $item = \array_shift($queue); // Nur anschauen

                try {
                    $result = $this->realMailService->sendTemplate(
                        $item['recipient'],
                        $item['subject'],
                        $item['template'],
                        $item['data'],
                    );

                    if ($result === true) {
                        \file_put_contents($path, \json_encode($queue, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
                        ++$sentCount;
                    } else {
                        throw new \Exception((string) $result);
                    }
                } catch (\Throwable $t) {
                    $item['attempts'] = ($item['attempts'] ?? 0) + 1;
                    if ($item['attempts'] < 3) {
                        $queue[] = $item;
                    }
                    \file_put_contents($path, \json_encode($queue, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
                }
            }
        }

        return $sentCount;
    }

    public function loadLogs(): array
    {
        return $this->realMailService->loadLogs();
    }

    public function saveLogs(array $logs): void
    {
        // Die Queue selbst speichert keine Logs, sie leitet den Befehl
        // an den echten Mail-Service (SmtpMailService) weiter.
        $this->realMailService->saveLogs($logs);
    }
}
