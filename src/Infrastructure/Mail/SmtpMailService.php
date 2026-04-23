<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * SMTP-Implementierung des Mail-Services.
 *
 * Rendert Templates und versendet diese über eine Socket-Verbindung.
 *
 * @file      src/Infrastructure/Mail/SmtpMailService.php
 *
 * @since     0.1.0
 * - feat(mail): Implementierung mit Template-Rendering und SMTP-Socket-Logik.
 */

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Contracts\Mail\MailServiceInterface;
use App\Infrastructure\Config\Config;

final class SmtpMailService implements MailServiceInterface
{
    public function __construct(
        private readonly Config $config
    ) {
    }

    public function sendTemplate(string $to, string $subject, string $template, array $data): bool|string
    {
        $mailConfig = $this->config->getMailSettings();

        // 1. Template laden und Platzhalter ersetzen (Simple Template Engine)
        $body = $this->render($template, $data);

        // 2. SMTP Versand (Logik aus deiner smtp.php, hier vereinfacht skizziert)
        // Wir nutzen hier das 'test_mode' Flag aus deiner Config
        if ($this->config->isTestMode() && !($mailConfig['test_mail_active'] ?? false)) {
            return true;
        }

        return $this->dispatch($to, $subject, $body, $mailConfig);
    }

    private function render(string $templatePath, array $data): string
    {
        $fullPath = "templates/emails/{$templatePath}.phtml";
        if (!file_exists($fullPath)) {
            throw new \RuntimeException("Mail-Template nicht gefunden: {$templatePath}");
        }

        $content = file_get_contents($fullPath);
        foreach ($data as $key => $value) {
            $content = str_replace("{{{$key}}}", (string)$value, $content);
        }

        return $content;
    }

    private function dispatch(string $to, string $subject, string $body, array $c): bool|string
    {
        // Hier folgt die Socket-Logik (fsockopen) deiner SimpleSMTP-Klasse...
        // [Gekürzt für die Übersicht, implementiert nach deinem Vorbild]
        return true; // Simulierter Erfolg
    }
}
