<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

/**
 * SMTP-Implementierung des Mail-Services.
 *
 * Rendert Templates und versendet diese über eine Socket-Verbindung basierend auf SimpleSMTP.
 *
 * Path: src/Infrastructure/Mail/SmtpMailService.php
 */

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Contracts\Mail\MailServiceInterface;
use App\Infrastructure\Config\Config;

final readonly class SmtpMailService implements MailServiceInterface
{
    public function __construct(
        private Config $config,
        private ?\PDO $pdo, // Datenbank-Verbindung
    ) {
    }

    public function sendTemplate(string $recipient, string $subject, string $template, array $data): bool|string
    {
        // Absicherung: Wenn kein Empfänger da ist, gar nicht erst versuchen zu senden
        if (empty(\trim($recipient))) {
            $this->logEmail('System', $subject, $template, 'Übersprungen: Kein Empfänger angegeben');

            return true;
        }

        $mailConfig = $this->config->getMailSettings();

        // 1. Template laden und Platzhalter ersetzen (Simple Template Engine)
        $body = $this->render($template, $data);

        // 2. Testmodus-Logik
        // SMTP Versand (Logik aus deiner smtp.php, hier vereinfacht skizziert)
        // Wir nutzen hier das 'test_mode' Flag aus deiner Config
        if ($this->config->isTestMode() && ($mailConfig['test_mail_active'] ?? false) === false) {
            $this->logEmail($recipient, $subject, $template, 'Testmodus (kein Versand)');

            return true;
        }

        // 3. Versand und Logging
        $status = $this->dispatch($recipient, $subject, $body, $mailConfig);
        $this->logEmail($recipient, $subject, $template, $status);

        return $status;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function render(string $templatePath, array $data): string
    {
        $root     = $this->config->get('root_path');
        $fullPath = $root . "/templates/emails/{$templatePath}.phtml";

        if (! \file_exists($fullPath)) {
            throw new \RuntimeException("Mail-Template nicht gefunden: {$fullPath}");
        }

        // 1. Daten für das Template verfügbar machen
        \extract($data);

        // 2. Output Buffering starten, um das PHP-Template zu "fangen"
        \ob_start();
        include $fullPath;
        $content = \ob_get_clean();

        // 3. Legacy-Support: Falls noch {{variable}} Syntax im Template ist
        foreach ($data as $key => $value) {
            // Nur skalare Werte (Text/Zahlen) ersetzen, keine Objekte!
            if (\is_scalar($value)) {
                $content = \str_replace("{{{$key}}}", (string) $value, (string) $content);
            }
        }

        return (string) $content;
    }

    /**
     * @param array<string, mixed> $smtpConfig
     */
    private function dispatch(string $recipient, string $subject, string $body, array $smtpConfig): bool|string
    {
        $host = $smtpConfig['host'] ?? '';
        $port = (int) ($smtpConfig['port'] ?? 465);
        $user = $smtpConfig['user'] ?? '';
        $pass = $smtpConfig['pass'] ?? '';
        $from = $smtpConfig['from'] ?? '';

        $protocol = $port === 465 ? 'ssl://' : '';
        $socket   = @\fsockopen($protocol . $host, $port, $errno, $errstr, 15);

        if (! $socket) {
            return "Verbindung fehlgeschlagen: $errstr ($errno)";
        }

        // 1. Begrüßung abwarten (Code 220)
        if (! $this->checkResponse($socket, '220')) {
            return 'Server meldet sich nicht (Timeout)';
        }

        // 2. EHLO senden
        \fwrite($socket, 'EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n");
        if (! $this->checkResponse($socket, '250')) {
            return 'EHLO abgelehnt';
        }

        // 3. Login starten
        \fwrite($socket, "AUTH LOGIN\r\n");
        $this->getServerResponse($socket); // 334 erwartet

        \fwrite($socket, \base64_encode((string) $user) . "\r\n");
        $this->getServerResponse($socket);

        \fwrite($socket, \base64_encode($pass) . "\r\n");
        if (! $this->checkResponse($socket, '235')) {
            return 'SMTP Login fehlgeschlagen (Daten prüfen)';
        }

        // 4. Absender & Empfänger
        \fwrite($socket, "MAIL FROM: <$from>\r\n");
        $this->getServerResponse($socket);

        \fwrite($socket, "RCPT TO: <$recipient>\r\n");
        if (! $this->checkResponse($socket, '250')) {
            return "Empfänger $recipient wurde vom Server abgelehnt";
        }

        // 5. Daten senden
        \fwrite($socket, "DATA\r\n");
        $this->getServerResponse($socket); // 354 erwartet

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: <$from>\r\n";
        $headers .= "To: <$recipient>\r\n";
        $headers .= 'Subject: =?UTF-8?B?' . \base64_encode($subject) . "?=\r\n\r\n";

        \fwrite($socket, $headers . $body . "\r\n.\r\n");
        if (! $this->checkResponse($socket, '250')) {
            return 'E-Mail Daten wurden nicht akzeptiert';
        }

        \fwrite($socket, "QUIT\r\n");
        \fclose($socket);

        return true;
    }

    /**
     * Hilfsmethode: Prüft ob der Server mit dem erwarteten Code antwortet
     */
    private function checkResponse($socket, string $expectedCode): bool
    {
        $response = $this->getServerResponse($socket);

        return \str_starts_with($response, $expectedCode);
    }

    /**
     * @param resource $socket
     */
    private function getServerResponse($socket): string
    {
        $response = '';
        while ($str = \fgets($socket, 515)) {
            $response .= $str;
            // SMTP Zeilenende: Letzte Zeile hat ein Leerzeichen nach dem Code (z.B. "250 ")
            if (\preg_match('/^\d{3} /', $str)) {
                break;
            }
        }

        return $response;
    }

    private function logEmail(string $recipient, string $subject, string $template, bool|string $status): void
    {
        $cfg        = $this->config->get('storage_config')['mail_log'];
        $maxEntries = (int) $this->config->get('mail_log_max_entries', 200);

        if ($cfg['type'] === 'mysql') {
            // 1. Eintrag einfügen
            $stmt = $this->pdo->prepare("INSERT INTO {$cfg['table']} (timestamp, recipient, subject, template, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                \date('Y-m-d H:i:s'),
                $recipient,
                $subject,
                $template,
                $status === true ? 'Erfolg' : 'Fehler: ' . $status,
            ]);

            // 2. Cleanup (Optional: Hält die Datenbank schlank wie bei JSON)
            // Wir löschen alle alten Einträge, die über das Limit hinausgehen
            $this->pdo->exec("DELETE FROM {$cfg['table']} WHERE id NOT IN (
            SELECT id FROM (
                SELECT id FROM {$cfg['table']} ORDER BY timestamp DESC LIMIT $maxEntries
            ) foo
        )");

            return;
        }

        // --- Alter JSON Code ---
        $path = $this->config->get('root_path') . '/' . $this->config->get('storage_path_prefix') . $cfg['file'];
        $logs = \file_exists($path) ? \json_decode((string) \file_get_contents($path), true) : [];
        \array_unshift($logs, [
            'timestamp' => \date('Y-m-d H:i:s'),
            'recipient' => $recipient,
            'subject'   => $subject,
            'template'  => $template,
            'status'    => $status === true ? 'Erfolg' : 'Fehler: ' . $status,
        ]);
        $logs = \array_slice($logs, 0, $maxEntries);
        \file_put_contents($path, \json_encode($logs, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
    }

    /**
     * Speichert eine Liste von Mail-Logs (wichtig für Migration/Sync).
     */
    public function saveLogs(array $logs): void
    {
        $cfg = $this->config->get('storage_config')['mail_log'];

        if ($cfg['type'] === 'mysql' && $this->pdo) {
            // Wir nutzen eine Transaktion für maximale Bulk-Geschwindigkeit
            $this->pdo->beginTransaction();

            try {
                $stmt = $this->pdo->prepare("REPLACE INTO {$cfg['table']} (id, timestamp, recipient, subject, template, status) VALUES (?, ?, ?, ?, ?, ?)");
                foreach ($logs as $id => $log) {
                    $stmt->execute([
                        $id,
                        $log['timestamp'] ?? null,
                        $log['recipient'] ?? null,
                        $log['subject'] ?? null,
                        $log['template'] ?? null,
                        $log['status'] ?? null,
                    ]);
                }
                $this->pdo->commit();
            } catch (\Exception $e) {
                $this->pdo->rollBack();

                throw $e;
            }
        } else {
            // Fallback: Normales JSON-Speichern
            $path = $this->config->get('root_path') . $this->config->get('storage_path_prefix') . $cfg['file'];
            \file_put_contents($path, \json_encode($logs, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
        }
    }

    public function loadLogs(): array
    {
        $cfg = $this->config->get('storage_config')['mail_log'];

        if ($cfg['type'] === 'mysql') {
            if (! $this->pdo) {
                return [];
            }

            // Wir laden die neuesten zuerst
            return $this->pdo->query("SELECT * FROM {$cfg['table']} ORDER BY timestamp DESC")->fetchAll();
        }

        $path = $this->config->get('root_path') . '/' . $this->config->get('storage_path_prefix') . $cfg['file'];
        if (! \file_exists($path)) {
            return [];
        }

        return (array) \json_decode((string) \file_get_contents($path), true) ?? [];
    }
}
