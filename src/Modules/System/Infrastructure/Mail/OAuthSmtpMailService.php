<?php

declare(strict_types=1);

namespace App\Modules\System\Infrastructure\Mail;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\OAuth;
use PHPMailer\PHPMailer\PHPMailer;
use TheNetworg\OAuth2\Client\Provider\Azure;

final class OAuthSmtpMailService extends AbstractMailService
{
    protected function dispatch(string $recipient, string $subject, string $body, array $transportConfig, ?string $replyTo = null, array $attachments = []): bool|string
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $transportConfig['host'] ?? 'smtp.office365.com';
            $mail->SMTPAuth = true;
            $mail->AuthType = 'XOAUTH2';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = (int) ($transportConfig['port'] ?? 587);

            $emailAddress = $transportConfig['user'] ?? '';

            $provider = new Azure([
                'clientId' => $transportConfig['clientId'] ?? '',
                'clientSecret' => $transportConfig['clientSecret'] ?? '',
                'tenant' => $transportConfig['tenantId'] ?? '',
            ]);

            $mail->setOAuth(
                new OAuth([
                    'provider' => $provider,
                    'clientId' => $transportConfig['clientId'] ?? '',
                    'clientSecret' => $transportConfig['clientSecret'] ?? '',
                    'userName' => $emailAddress,
                ]),
            );

            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->setFrom($transportConfig['from'] ?? '', $this->config->get('vereins_name', 'KGA'));
            $mail->addAddress($recipient);

            if ($replyTo !== null && \filter_var($replyTo, \FILTER_VALIDATE_EMAIL)) {
                $mail->addReplyTo($replyTo);
            }

            foreach ($attachments as $att) {
                $mail->addStringAttachment($att['content'], $att['name'], 'base64', $att['mime'] ?? 'application/pdf');
            }

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;

            $mail->send();

            return true;
        } catch (PHPMailerException $e) {
            return 'PHPMailer OAuth Fehler: ' . $mail->ErrorInfo;
        }
    }
}
