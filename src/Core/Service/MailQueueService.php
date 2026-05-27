<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Mail\MailServiceInterface;

/**
 * Service zur zeitversetzten E-Mail-Verarbeitung via Queueing.
 *
 * Abstrahiert und entkoppelt den physischen SMTP-Verbindungsprozess von Web-Requests.
 * Speichert ausgehende E-Mails als JSON-Spool oder DB-Queue und wickelt den Versand blockweise ab.
 * Kontext: Performance- und Ausfallsicherheits-Layer für den E-Mail-Subversand.
 *
 * Verwaltet den E-Mail-Versand über eine Queue, um Systemressourcen zu schonen.
 * Unterstützt die Speicherung von E-Mails in einer MySQL-Datenbank oder in einer JSON-Datei,
 * bevor sie durch den eigentlichen SMTP-Service verarbeitet werden.
 *
 * Path: src/Core/Service/MailQueueService.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class MailQueueService implements MailServiceInterface
{
    /**
     * @var ConfigInterface
     * @var \PDO|null
     * @var MailServiceInterface
     */
    public function __construct(
        private ?\PDO $pdo,
        private ConfigInterface $config,
        private MailServiceInterface $realMailService, // Der echte SMTP-Service
    ) {
    }

    /**
     * Reiht eine neue E-Mail mit Template-Referenz und Variablen-Payload in die Warteschlange ein.
     *
     * @param string $recipient Empfängeradresse.
     * @param string $subject   E-Mail-Betreff.
     * @param string $template  Pfad zum E-Mail-Template.
     * @param array  $data      Daten-Payload für das Template.
     *
     * @return bool True bei Erfolg, Fehlermeldung als String bei Misserfolg.
     */
    public function sendTemplate(string $recipient, string $subject, string $template, array $data): bool
    {
        $cfg     = $this->config->get('storage_config')['mail_queue'];
        $payload = \json_encode($data);

        if ($cfg['type'] === 'mysql' && $this->pdo instanceof \PDO) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO mail_queue (recipient, subject, template, data, created_at) VALUES (?, ?, ?, ?, ?)',
            );
            $stmt->execute([$recipient, $subject, $template, $payload, \date('Y-m-d H:i:s')]);
        } else {
            // BUGFIX: Sicherer Pfad mit Schrägstrich
            $path = \rtrim(
                (string) $this->config->get('root_path'),
                '/\\',
            ) . '/' . \ltrim(
                (string) $this->config->get('storage_path_prefix'),
                '/\\',
            ) . $cfg['file'];
            $queue   = \file_exists($path) ? \json_decode((string) \file_get_contents($path), true) : [];
            $queue[] = [
                'recipient'  => $recipient,
                'subject'    => $subject,
                'template'   => $template,
                'data'       => $data,
                'attempts'   => 0,
                'created_at' => \date('Y-m-d H:i:s'),
            ];
            \file_put_contents($path, \json_encode($queue, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
        }

        return true; // "Erfolg", da in Queue gespeichert
    }

    /**
     * Verarbeitet blockweise die anstehenden Mails aus der Warteschlange.
     * Verwendet bei MySQL einen Locking-Mechanismus (attempts + 100) gegen Race-Conditions,
     * zählt Fehlversuche (max 3 Attempts) hoch und protokolliert Fehlermeldungen bei Abbruch.
     *
     * @param int $limit Maximale Anzahl der in diesem Durchlauf zu verarbeitenden Mails.
     *
     * @return int Anzahl der real erfolgreich versendeten E-Mails.
     */
    public function processQueue(int $limit = 5): int
    {
        $cfg       = $this->config->get('storage_config')['mail_queue'];
        $sentCount = 0;

        // --- A. MYSQL LOGIK ---
        if ($cfg['type'] === 'mysql' && $this->pdo instanceof \PDO) {
            // Markieren (Locking)
            $this->pdo->prepare(
                "UPDATE mail_queue SET attempts = attempts + 100 WHERE attempts < 3 LIMIT $limit",
            )->execute();

            // Jetzt holen wir uns die markierten Mails
            $stmt  = $this->pdo->query('SELECT * FROM mail_queue WHERE attempts >= 100 ORDER BY created_at ASC');
            $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($items as $item) {
                try {
                    $result = $this->realMailService->sendTemplate(
                        $item['recipient'],
                        $item['subject'],
                        $item['template'],
                        \json_decode((string) $item['data'], true),
                    );
                    if ($result !== true) {
                        throw new \Exception((string) $result);
                    }

                    $this->pdo->prepare('DELETE FROM mail_queue WHERE id = ?')->execute([$item['id']]);
                    ++$sentCount;
                } catch (\Throwable $t) {
                    $origAttempts = $item['attempts'] - 100 + 1;
                    $this->pdo->prepare('UPDATE mail_queue SET attempts = ?, last_error = ? WHERE id = ?')
                        ->execute([$origAttempts, $t->getMessage(), $item['id']]);
                }
            }
        } else {
            // --- B. JSON LOGIK ---

            // BUGFIX: Sicherer Pfad mit Schrägstrich
            $path = \rtrim(
                (string) $this->config->get('root_path'),
                '/\\',
            ) . '/' . \ltrim(
                (string) $this->config->get('storage_path_prefix'),
                '/\\',
            ) . $cfg['file'];
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

                    if ($result !== true) {
                        throw new \Exception((string) $result);
                    }

                    \file_put_contents($path, \json_encode($queue, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
                    ++$sentCount;
                } catch (\Throwable) {
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

    /**
     * Delegiert den Lesezugriff auf historische Versand-Logs an den zugrundeliegenden SMTP-Service.
     *
     * @return array<int, array<string, mixed>> Array mit Log-Einträgen.
     */
    public function loadLogs(): array
    {
        return $this->realMailService->loadLogs();
    }

    /**
     * Speichert Protokolle für den E-Mail-Versand.
     * Delegiert den Schreibzugriff für Log-Dateien an den SMTP-Dienst weiter.
     *
     * @param array<int, array<string, mixed>> $logs Liste der zu speichernden Log-Einträge.
     */
    public function saveLogs(array $logs): void
    {
        // Die Queue selbst speichert keine Logs, sie leitet den Befehl
        // an den echten Mail-Service (SmtpMailService) weiter.
        $this->realMailService->saveLogs($logs);
    }
}
