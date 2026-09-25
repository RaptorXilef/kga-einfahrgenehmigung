<?php

declare(strict_types=1);

namespace App\Modules\System\Infrastructure\Mail;

use Override;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

final class SmtpMailService extends AbstractMailService
{
    #[Override]
    protected function dispatch(string $recipient, string $subject, string $body, array $transportConfig, ?string $replyTo = null, array $attachments = []): bool|string
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = (string) ($transportConfig['host'] ?? '');
            $mail->SMTPAuth = true;
            $mail->Username = (string) ($transportConfig['user'] ?? '');
            $mail->Password = (string) ($transportConfig['pass'] ?? '');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Implicit TLS (Port 465)
            $mail->Port = (int) ($transportConfig['port'] ?? 465);

            if ($mail->Port === 587) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }

            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->setFrom((string) ($transportConfig['from'] ?? ''), $this->config->getString('vereins_name', 'KGA'));
            $mail->addAddress($recipient);

            if ($replyTo !== null && \filter_var($replyTo, \FILTER_VALIDATE_EMAIL)) {
                $mail->addReplyTo($replyTo);
            }

            foreach ($attachments as $att) {
                if (!\is_array($att)) {
                    continue;
                }
                $mail->addStringAttachment(
                    (string) ($att['content'] ?? ''),
                    (string) ($att['name'] ?? 'attachment.pdf'),
                    'base64',
                    (string) ($att['mime'] ?? 'application/pdf'),
                );
            }

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;

            $mail->send();

            return true;
        } catch (PHPMailerException) {
            return 'PHPMailer Fehler: ' . $mail->ErrorInfo;
        }
    }
}
