<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Standard SMTP-Versand unter Verwendung von PHPMailer.
 * Bietet volle Unterstützung für STARTTLS und sicheres Header-Encoding.
 */
final class SmtpMailService extends AbstractMailService
{
    protected function dispatch(string $recipient, string $subject, string $body, array $transportConfig, ?string $replyTo = null): bool|string
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $transportConfig['host'] ?? '';
            $mail->SMTPAuth = true;
            $mail->Username = $transportConfig['user'] ?? '';
            $mail->Password = $transportConfig['pass'] ?? '';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Implicit TLS (Port 465)
            $mail->Port = (int) ($transportConfig['port'] ?? 465);

            // Wechsel auf STARTTLS, falls Port 587 konfiguriert ist
            if ($mail->Port === 587) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }

            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->setFrom($transportConfig['from'] ?? '', $this->config->get('vereins_name', 'KGA'));
            $mail->addAddress($recipient);

            if ($replyTo !== null && \filter_var($replyTo, \FILTER_VALIDATE_EMAIL)) {
                $mail->addReplyTo($replyTo);
            }

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;

            $mail->send();

            return true;
        } catch (PHPMailerException $e) {
            return 'PHPMailer Fehler: ' . $mail->ErrorInfo;
        }
    }
}
