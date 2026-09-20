<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use TheNetworg\OAuth2\Client\Provider\Azure;
use Throwable;

/**
 * Hochperformanter Versand via Microsoft Graph API REST Call.
 * Benötigt kein überladenes Microsoft SDK, sondern greift direkt auf den Endpunkt zu.
 */
final class MicrosoftGraphMailService extends AbstractMailService
{
    protected function dispatch(string $recipient, string $subject, string $body, array $transportConfig, ?string $replyTo = null, array $attachments = []): bool|string
    {
        try {
            $provider = new Azure([
                'clientId' => $transportConfig['clientId'] ?? '',
                'clientSecret' => $transportConfig['clientSecret'] ?? '',
                'tenant' => $transportConfig['tenantId'] ?? '',
                'defaultEndPointVersion' => '2.0',
            ]);

            // Token via "Client Credentials Grant" (Application Permissions: Mail.Send) abrufen
            $accessToken = $provider->getAccessToken('client_credentials', [
                'scope' => 'https://graph.microsoft.com/.default',
            ]);

            $from = $transportConfig['from'] ?? '';

            $payload = [
                'message' => [
                    'subject' => $subject,
                    'body' => [
                        'contentType' => 'HTML',
                        'content' => $body,
                    ],
                    'toRecipients' => [
                        [
                            'emailAddress' => [
                                'address' => $recipient,
                            ],
                        ],
                    ],
                ],
                'saveToSentItems' => 'true',
            ];

            if ($replyTo !== null && \filter_var($replyTo, \FILTER_VALIDATE_EMAIL)) {
                $payload['message']['replyTo'] = [
                    [
                        'emailAddress' => [
                            'address' => $replyTo,
                        ],
                    ],
                ];
            }

            // NEU: Graph API Attachments Struktur
            $graphAttachments = [];
            foreach ($attachments as $att) {
                $graphAttachments[] = [
                    '@odata.type' => '#microsoft.graph.fileAttachment',
                    'name' => $att['name'],
                    'contentType' => $att['mime'] ?? 'application/pdf',
                    'contentBytes' => \base64_encode($att['content']),
                ];
            }

            if (!empty($graphAttachments)) {
                $payload['message']['attachments'] = $graphAttachments;
            }

            // Guzzle HTTP Client Aufruf via League OAuth2 Wrapper
            $request = $provider->getAuthenticatedRequest(
                'POST',
                "https://graph.microsoft.com/v1.0/users/{$from}/sendMail",
                $accessToken,
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                    'body' => \json_encode($payload, \JSON_THROW_ON_ERROR),
                ],
            );

            $provider->getParsedResponse($request);

            return true;

        } catch (Throwable $e) {
            return 'Microsoft Graph API Fehler: ' . $e->getMessage();
        }
    }
}
