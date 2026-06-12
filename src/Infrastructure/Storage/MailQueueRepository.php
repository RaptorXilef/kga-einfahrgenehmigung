<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\MailQueueRepositoryInterface;

/**
 * Implementierung des Mail-Queue-Repositories.
 * Legt ausgehende E-Mails in der Datenbank oder JSON-Datei ab und holt sie
 * gestaffelt (Batch-Verarbeitung) für den asynchronen Versand wieder ab.
 *
 * Path: src/Infrastructure/Storage/MailQueueRepository.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class MailQueueRepository implements MailQueueRepositoryInterface
{
    use SafeJsonWriterTrait;

    public function __construct(
        private ?\PDO $pdo,
        private ConfigInterface $config,
    ) {
    }

    /**
     * Hinzufügen
     *
     * Reiht eine E-Mail als JSON-codierten String in die MySQL- oder JSON-Queue ein.
     *
     * @param string               $recipient E-Mail-Empfänger.
     * @param string               $subject   Betreff.
     * @param string               $template  Template-Key.
     * @param array<string, mixed> $data      Daten für das Template.
     */
    public function enqueue(string $recipient, string $subject, string $template, array $data): void
    {
        $cfg     = $this->config->get('storage_config')['mail_queue'];
        $payload = \json_encode($data, \JSON_UNESCAPED_UNICODE);

        if ($cfg['type'] === 'mysql' && $this->pdo instanceof \PDO) {
            $stmt = $this->pdo->prepare('INSERT INTO `mail_queue` (recipient, subject, template, data, created_at) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$recipient, $subject, $template, $payload, APP_REQUEST_TIME_STR]);
        } else {
            $path    = \rtrim((string) $this->config->get('root_path'), '/\\') . '/' . \ltrim((string) $this->config->get('storage_path_prefix'), '/\\') . $cfg['file'];
            $queue   = JsonHelper::read($path);
            $queue[] = [
                'recipient'  => $recipient,
                'subject'    => $subject,
                'template'   => $template,
                'data'       => $data,
                'attempts'   => 0,
                'created_at' => APP_REQUEST_TIME_STR,
            ];
            $this->writeJsonSafely($path, $queue);
        }
    }

    /**
     * Abarbeiten
     *
     * Verarbeitet einen Batch ausstehender E-Mails aus der MySQL- oder JSON-Queue.
     * Erhöht die Versuchsanzahl bei Fehlern.
     *
     * @param int      $limit     Max. Anzahl Mails.
     * @param callable $processor Funktion zum Senden.
     *
     * @return int Anzahl erfolgreich versendeter E-Mails.
     */
    public function processBatch(int $limit, callable $processor): int
    {
        $cfg       = $this->config->get('storage_config')['mail_queue'];
        $sentCount = 0;

        // =========================================================================
        // MYSQL-MODUS
        // =========================================================================
        if ($cfg['type'] === 'mysql' && $this->pdo instanceof \PDO) {
            $this->pdo->exec("UPDATE `mail_queue` SET attempts = attempts + 100 WHERE attempts < 3 LIMIT $limit");
            $items = $this->pdo->query('SELECT * FROM `mail_queue` WHERE attempts >= 100 ORDER BY created_at ASC')->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($items as $item) {
                try {
                    $processor($item['recipient'], $item['subject'], $item['template'], JsonHelper::decode((string) $item['data']));
                    $this->pdo->prepare('DELETE FROM `mail_queue` WHERE id = ?')->execute([$item['id']]);
                    ++$sentCount;
                } catch (\Throwable $t) {
                    // Fehler der Mail-Queue in die Server-Logs schreiben, damit ich sie debuggen kann!
                    \error_log("MailQueue Error [ID {$item['id']} / Template {$item['template']}]: " . $t->getMessage());

                    $origAttempts = $item['attempts'] - 100 + 1;

                    // Tote Mails nach 3 Versuchen löschen, statt die DB unendlich aufzublähen!
                    if ($origAttempts >= 3) {
                        $this->pdo->prepare('DELETE FROM `mail_queue` WHERE id = ?')->execute([$item['id']]);
                    } else {
                        $this->pdo->prepare('UPDATE `mail_queue` SET attempts = ? WHERE id = ?')->execute([$origAttempts, $item['id']]);
                    }
                }
            }

            return $sentCount;
        }

        // =========================================================================
        // JSON-MODUS (Dateibasiert)
        // =========================================================================
        $path = \rtrim((string) $this->config->get('root_path'), '/\\') . '/' . \ltrim((string) $this->config->get('storage_path_prefix'), '/\\') . $cfg['file'];

        if (! \file_exists($path)) {
            return 0;
        }

        // Sicheres Auslesen und Verarbeiten mit exklusivem File-Lock (LOCK_EX)
        $fp = @\fopen($path, 'c+');
        if ($fp && \flock($fp, \LOCK_EX)) {

            // fstat() statt filesize(), um den PHP Stat-Cache zu umgehen!
            $stat  = \fstat($fp);
            $size  = $stat['size'] ?? 0;
            $queue = $size > 0 ? JsonHelper::decode((string) \fread($fp, $size)) : [];

            if (! empty($queue)) {
                $actualLimit = \min($limit, \count($queue));

                for ($i = 0; $i < $actualLimit; ++$i) {
                    $item = \array_shift($queue);

                    try {
                        $processor($item['recipient'], $item['subject'], $item['template'], $item['data']);
                        ++$sentCount;
                    } catch (\Throwable $t) {
                        // Fehler der Mail-Queue in die Server-Logs schreiben!
                        \error_log("MailQueue Error [JSON / Template {$item['template']}]: " . $t->getMessage());

                        $item['attempts'] = ($item['attempts'] ?? 0) + 1;
                        // Nach dem 3. Versuch fliegt sie automatisch raus, da sie nicht wieder angehängt wird
                        if ($item['attempts'] < 3) {
                            $queue[] = $item; // Bei Fehler hinten wieder anstellen
                        }
                    }
                }

                // Einmaliges Zurückschreiben des modifizierten Queue-Zustands
                \ftruncate($fp, 0);
                \fseek($fp, 0);
                $json = \json_encode($queue, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE);
                if (\fwrite($fp, $json) === false) {
                    throw new \RuntimeException('Kritischer Schreibfehler: Konnte verarbeitete Mail-Queue nicht speichern.');
                }
                \fflush($fp);
            }

            \flock($fp, \LOCK_UN);
            \fclose($fp);
        }

        return $sentCount;
    }
}
