<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Contracts\Mail\MailServiceInterface;
use App\Infrastructure\Config\Config;

/**
 * Low-Level SMTP-E-Mail-Dienst zur Direktübertragung über Sockets.
 * Baut native Netzwerkverbindungen via 'fsockopen' auf, implementiert das RFC-konforme SMTP-Protokoll
 * (EHLO, AUTH LOGIN, MAIL FROM, RCPT TO, DATA) inklusive Base64-Verschlüsselung,
 * verarbeitet PHTML-E-Mail-Templates mit String-Platzhaltern und führt Auditing-Logs im gewählten Backend.
 * Kontext: Die physische Mail-Engine der Anwendung.
 *
 * Path: src/Infrastructure/Mail/SmtpMailService.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class SmtpMailService implements MailServiceInterface
{
    public function __construct(
        private ?\PDO $pdo, // Datenbank-Verbindung
        private Config $config,
    ) {
    }

    /**
     * Verarbeitet und versendet eine E-Mail basierend auf einem Template.
     * Fängt leere Empfänger ab, liest Mail-Konfigurationen aus, prüft den Testmodus-Status
     * und übergibt an den Socket-Dispatcher, bevor ein Log-Eintrag generiert wird.
     *
     * @param string               $recipient Der Ziel-Empfänger.
     * @param string               $subject   Der E-Mail-Betreff.
     * @param string               $template  Das .phtml-Template im Ordner templates/emails/.
     * @param array<string, mixed> $data      Variablen zur Injektion in das Template.
     *
     * @return bool|string True bei Erfolg, andernfalls eine Fehlermeldung als String.
     */
    public function sendTemplate(string $recipient, string $subject, string $template, array $data): bool|string
    {
        // Absicherung: Wenn kein Empfänger da ist, gar nicht erst versuchen zu senden
        if (\in_array(\trim($recipient), ['', '0'], true)) {
            // $data am Ende hinzugefügt
            $this->logEmail('System', $subject, $template, 'Übersprungen: Kein Empfänger angegeben', $data);

            return true;
        }

        $mailConfig = $this->config->getMailSettings();

        // 1. Template laden und Platzhalter ersetzen (Simple Template Engine)
        $body = $this->render($template, $data);

        // 2. Testmodus-Logik
        // SMTP Versand (Logik aus deiner smtp.php, hier vereinfacht skizziert)
        // Wir nutzen hier das 'test_mode' Flag aus deiner Config
        if ($this->config->isTestMode() && ($mailConfig['test_mail_active'] ?? false) === false) {
            // $data am Ende hinzugefügt
            $this->logEmail($recipient, $subject, $template, 'Testmodus (kein Versand)', $data);

            return true;
        }

        // 3. Versand und Logging
        $status = $this->dispatch($recipient, $subject, $body, $mailConfig);
        // $data am Ende hinzugefügt
        $this->logEmail($recipient, $subject, $template, $status, $data);

        return $status;
    }

    /**
     * Rendert das PHTML-E-Mail-Template über den Output-Buffer und ersetzt Platzhalter.
     * Sucht im gerenderten HTML nach `{{key}}` Mustern und ersetzt diese mit skalaren Array-Inhalten.
     *
     * @param string               $templatePath Der Dateiname des Templates.
     * @param array<string, mixed> $data         Die Injektionsvariablen.
     *
     * @return string Das finale, versandbereite HTML-Markup.
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
            if (! \is_scalar($value)) {
                continue;
            }

            $content = \str_replace("{{{$key}}}", (string) $value, (string) $content);
        }

        return (string) $content;
    }

    /**
     * Führt die physische SMTP-Socket-Kommunikation mit dem Mailserver durch.
     * Abstrahiert SSL-Protokolle, authentifiziert sich via AUTH LOGIN und überträgt UTF-8 / Base64-kodierte Header.
     *
     * @param string               $recipient  Empfänger-E-Mail.
     * @param string               $subject    Betreff-Zeile.
     * @param string               $body       Der gerenderte HTML-Textkörper.
     * @param array<string, mixed> $smtpConfig Serverdaten (host, port, user, pass, from).
     *
     * @return bool|string True bei SMTP-Erfolg (Code 250), andernfalls Fehlermeldung.
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
     * Prüft, ob die Server-Antwort mit dem erwarteten numerischen SMTP-Statuscode beginnt.
     *
     * @param resource $socket
     */
    private function checkResponse($socket, string $expectedCode): bool
    {
        $response = $this->getServerResponse($socket);

        return \str_starts_with($response, $expectedCode);
    }

    /**
     * Liest zeilenweise die Antwort-Buffer des SMTP-Servers bis zum abschließenden Statuscode aus.
     *
     * @param resource $socket
     *
     * @return string Die gesammelte Serverantwort.
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

    /**
     * Schreibt einen Eintrag in das E-Mail-Versandprotokoll und begrenzt die Historie (Capping).
     * Unterstützt MySQL-Einträge inklusive Tabellen-Bereinigung via Subquery oder historisierende JSON-Dateien.
     *
     * @param string               $recipient Empfänger.
     * @param string               $subject   Betreff.
     * @param string               $template  Genutztes Template.
     * @param bool|string          $status    Das Ergebnis der dispatch-Methode.
     * @param array<string, mixed> $data      Mitgesendete Rohdaten-Payload.
     */
    private function logEmail(
        string $recipient,
        string $subject,
        string $template,
        bool|string $status,
        array $data = [],
    ): void {
        $cfg        = $this->config->get('storage_config')['mail_log'];
        $maxEntries = (int) $this->config->get('mail_log_max_entries', 200);
        $statusStr  = $status === true ? 'Erfolg' : 'Fehler: ' . $status;

        if ($cfg['type'] === 'mysql') {
            // Hier wird das data-Array als JSON-String in die DB geladen
            $stmt = $this->pdo->prepare("INSERT INTO {$cfg['table']} (
                timestamp,
                recipient,
                subject,
                template,
                status,
                data
            ) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                \date('Y-m-d H:i:s'),
                $recipient,
                $subject,
                $template,
                $statusStr,
                \json_encode($data, \JSON_UNESCAPED_UNICODE),
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
        $path = \rtrim(
            (string) $this->config->get('root_path'),
            '/\\',
        ) . '/' . \ltrim((string) $this->config->get('storage_path_prefix'), '/\\') . $cfg['file'];
        $logs = \file_exists($path) ? \json_decode((string) \file_get_contents($path), true) : [];
        \array_unshift($logs, [
            'timestamp' => \date('Y-m-d H:i:s'),
            'recipient' => $recipient,
            'subject'   => $subject,
            'template'  => $template,
            'status'    => $statusStr,
            'data'      => $data,
        ]);
        $logs = \array_slice($logs, 0, $maxEntries);
        \file_put_contents($path, \json_encode($logs, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
    }

    /**
     * Ermöglicht das Batch-Überschreiben / Wiederherstellen von E-Mail-Logs (z.B. bei System-Restores).
     * Verwendet SQL-Transaktionen (Commit/Rollback) für hohe Konsistenz im DB-Betrieb.
     *
     * Speichert eine Liste von Mail-Logs (wichtig für Migration/Sync).
     *
     * @param array<int, array<string, mixed>> $logs
     */
    public function saveLogs(array $logs, bool $forceSql = false): void
    {
        $cfg    = $this->config->get('storage_config')['mail_log'];
        $useSql = $forceSql || (($cfg['type'] ?? 'json') === 'mysql');

        if ($useSql && $this->pdo instanceof \PDO) {
            $this->pdo->beginTransaction();

            try {
                // REPLACE sorgt dafür, dass IDs bei Migration nicht dupliziert werden
                $stmt = $this->pdo->prepare("REPLACE INTO {$cfg['table']} (
                id,
                timestamp,
                recipient,
                subject,
                template,
                status,
                data
                ) VALUES (?, ?, ?, ?, ?, ?, ?)");
                foreach ($logs as $id => $log) {
                    $rawPayload = $log['data'] ?? null;
                    $stmt->execute([
                        $id,
                        $log['timestamp'] ?? null,
                        $log['recipient'] ?? null,
                        $log['subject'] ?? null,
                        $log['template'] ?? null,
                        $log['status'] ?? null,
                        \is_array($rawPayload) ? \json_encode($rawPayload, \JSON_UNESCAPED_UNICODE)
                            : $rawPayload,
                    ]);
                }
                $this->pdo->commit();
            } catch (\Exception $e) {
                $this->pdo->rollBack();

                throw $e;
            }
            if ($forceSql) {
                return;
            } // Beenden, falls MySQL via Migration erzwungen wurde
        }

        if (! $forceSql) {
            $path = \rtrim(
                (string) $this->config->get('root_path'),
                '/\\',
            ) . '/' . \ltrim(
                (string) $this->config->get('storage_path_prefix'),
                '/\\',
            ) . $cfg['file'];
            \file_put_contents(
                $path,
                \json_encode($logs, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE),
            );
        }
    }

    /**
     * Lädt die chronologische Liste aller E-Mail-Logs absteigend nach Zeitstempel.
     *
     * @return array<int, array<string, mixed>>
     */
    public function loadLogs(): array
    {
        $cfg = $this->config->get('storage_config')['mail_log'];

        if ($cfg['type'] === 'mysql') {
            if (! $this->pdo instanceof \PDO) {
                return [];
            }

            // Wir laden die neuesten zuerst
            return $this->pdo->query("SELECT * FROM {$cfg['table']} ORDER BY timestamp DESC")->fetchAll();
        }

        $path = \rtrim(
            (string) $this->config->get('root_path'),
            '/\\',
        ) . '/' . \ltrim(
            (string) $this->config->get('storage_path_prefix'),
            '/\\',
        ) . $cfg['file'];
        if (! \file_exists($path)) {
            return [];
        }

        return (array) \json_decode((string) \file_get_contents($path), true) ?? [];
    }
}
