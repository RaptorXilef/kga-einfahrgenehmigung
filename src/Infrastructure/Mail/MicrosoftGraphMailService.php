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
    protected function dispatch(string $recipient, string $subject, string $body, array $transportConfig, ?string $replyTo = null): bool|string
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

            // Response abrufen. Die Graph API liefert bei Erfolg einen 202 Accepted Status ohne Body.
            $provider->getParsedResponse($request);

            return true;

        } catch (Throwable $e) {
            return 'Microsoft Graph API Fehler: ' . $e->getMessage();
        }
    }
}
