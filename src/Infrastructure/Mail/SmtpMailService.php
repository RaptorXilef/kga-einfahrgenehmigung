<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * SMTP-Implementierung des Mail-Services.
 *
 * Rendert Templates und versendet diese über eine Socket-Verbindung basierend auf SimpleSMTP.
 *
 * @file      src/Infrastructure/Mail/SmtpMailService.php
 *
 * @copyright (c) 2026 Felix Maywald. All rights reserved.
 * @license   https://github.com/RaptorXilef/kga-einfahrgenehmigung/blob/main/LICENSE
 *
 * @link      https://github.com/RaptorXilef/kga-einfahrgenehmigung/
 *
 * @author    Felix Maywald (@RaptorXilef)
 */

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Contracts\Mail\MailServiceInterface;
use App\Infrastructure\Config\Config;

final readonly class SmtpMailService implements MailServiceInterface
{
    public function __construct(
        private Config $config,
    ) {
    }

    public function sendTemplate(string $recipient, string $subject, string $template, array $data): bool|string
    {
        $mailConfig = $this->config->getMailSettings();

        // 1. Template laden und Platzhalter ersetzen (Simple Template Engine)
        $body = $this->render($template, $data);

        // Testmodus-Logik
        // 2. SMTP Versand (Logik aus deiner smtp.php, hier vereinfacht skizziert)
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
        $socket   = \fsockopen($protocol . $host, $port, $errno, $errstr, 15);

        if (! $socket) {
            return "Verbindung fehlgeschlagen: $errstr ($errno)";
        }

        $this->getServerResponse($socket); // Begrüßung

        \fwrite($socket, 'EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n");
        $this->getServerResponse($socket);

        \fwrite($socket, "AUTH LOGIN\r\n");
        $this->getServerResponse($socket);

        \fwrite($socket, \base64_encode((string) $user) . "\r\n");
        $this->getServerResponse($socket);

        \fwrite($socket, \base64_encode($pass) . "\r\n");
        $this->getServerResponse($socket);

        \fwrite($socket, "MAIL FROM: <$from>\r\n");
        $this->getServerResponse($socket);

        \fwrite($socket, "RCPT TO: <$recipient>\r\n");
        $this->getServerResponse($socket);

        \fwrite($socket, "DATA\r\n");
        $this->getServerResponse($socket);

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: <$from>\r\n";
        $headers .= "To: <$recipient>\r\n";
        $headers .= 'Subject: =?UTF-8?B?' . \base64_encode($subject) . "?=\r\n\r\n";

        \fwrite($socket, $headers . $body . "\r\n.\r\n");
        $this->getServerResponse($socket);

        \fwrite($socket, "QUIT\r\n");
        \fclose($socket);

        return true;
    }

    /**
     * @param resource $socket
     */
    private function getServerResponse($socket): string
    {
        $response = '';
        while ($str = \fgets($socket, 515)) {
            $response .= $str;
            if (\str_contains($str, ' ')) {
                break;
            }
        }

        return $response;
    }

    private function logEmail(string $recipient, string $subject, string $template, bool|string $status): void
    {
        $logPath = $this->config->get('root_path') . '/storage/mail_log.json';
        $logs    = \file_exists($logPath) ? \json_decode((string) \file_get_contents($logPath), true) : [];

        $entry = [
            'timestamp' => \date('Y-m-d H:i:s'),
            'recipient' => $recipient,
            'subject'   => $subject,
            'template'  => $template,
            'status'    => $status === true ? 'Erfolg' : 'Fehler: ' . $status,
        ];

        \array_unshift($logs, $entry);
        $logs = \array_slice($logs, 0, 200); // Max 200 Einträge
        \file_put_contents($logPath, \json_encode($logs, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
    }
}
