<?php

declare(strict_types=1);

namespace App\Modules\System\Infrastructure\System;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\System\AnalyticsTrackerInterface;
use Override;

/**
 * Physischer HTTP-Client für das Google Analytics 4 Measurement Protocol.
 * Kapselt alle cURL-Aufrufe sicher in der Infrastructure-Schicht.
 */
final readonly class Ga4MeasurementClient implements AnalyticsTrackerInterface
{
    public function __construct(
        private ConfigInterface $config,
    ) {
    }

    #[Override]
    public function trackPageView(string $clientId, int $sessionId, string $pageLocation, string $pageTitle): void
    {
        $gaCfg = $this->config->getArray('ga4_server_side');
        $gaId = (string) ($gaCfg['measurement_id'] ?? '');
        $apiSecret = (string) ($gaCfg['api_secret'] ?? '');

        if ($gaId === '' || $apiSecret === '') {
            return;
        }

        $payload = [
            'client_id' => $clientId,
            'events' => [
                [
                    'name' => 'page_view',
                    'params' => [
                        'page_location' => $pageLocation,
                        'page_title' => $pageTitle,
                        'session_id' => $sessionId,
                        'engagement_time_msec' => 1,
                    ],
                ],
            ],
        ];

        $url = 'https://www.google-analytics.com/mp/collect?measurement_id=' . \urlencode($gaId) . '&api_secret=' . \urlencode($apiSecret);
        $ch = \curl_init($url);
        if ($ch === false) {
            return;
        }

        \curl_setopt_array($ch, [
            \CURLOPT_PROTOCOLS => \CURLPROTO_HTTPS,
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_POST => true,
            \CURLOPT_POSTFIELDS => \json_encode($payload),
            \CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            \CURLOPT_TIMEOUT_MS => 250,
        ]);
        \curl_exec($ch);
    }
}
