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

            return $this->sendConfiguredPhpMailer($mail, $recipient, $subject, $body, $transportConfig, $replyTo, $attachments);
        } catch (PHPMailerException) {
            return 'PHPMailer Fehler: ' . $mail->ErrorInfo;
        }
    }
}
