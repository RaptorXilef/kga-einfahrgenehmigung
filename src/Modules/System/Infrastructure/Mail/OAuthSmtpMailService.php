<?php

declare(strict_types=1);

namespace App\Modules\System\Infrastructure\Mail;

use Override;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\OAuth;
use PHPMailer\PHPMailer\PHPMailer;
use TheNetworg\OAuth2\Client\Provider\Azure;

final class OAuthSmtpMailService extends AbstractMailService
{
    #[Override]
    protected function dispatch(string $recipient, string $subject, string $body, array $transportConfig, ?string $replyTo = null, array $attachments = []): bool|string
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = (string) ($transportConfig['host'] ?? 'smtp.office365.com');
            $mail->SMTPAuth = true;
            $mail->AuthType = 'XOAUTH2';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = (int) ($transportConfig['port'] ?? 587);

            $emailAddress = (string) ($transportConfig['user'] ?? '');

            $provider = new Azure([
                'clientId' => (string) ($transportConfig['clientId'] ?? ''),
                'clientSecret' => (string) ($transportConfig['clientSecret'] ?? ''),
                'tenant' => (string) ($transportConfig['tenantId'] ?? ''),
            ]);

            $mail->setOAuth(
                new OAuth([
                    'provider' => $provider,
                    'clientId' => (string) ($transportConfig['clientId'] ?? ''),
                    'clientSecret' => (string) ($transportConfig['clientSecret'] ?? ''),
                    'userName' => $emailAddress,
                ]),
            );

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
            return 'PHPMailer OAuth Fehler: ' . $mail->ErrorInfo;
        }
    }
}
