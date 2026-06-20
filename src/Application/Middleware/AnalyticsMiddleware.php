<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Http\ServerRequest;
use App\Application\Session\SessionManager;
use App\Contracts\Application\MiddlewareInterface;
use App\Contracts\Config\ConfigInterface;

/**
 * Sendet Serverseitige Events an Google Analytics (GA4).
 * Asynchron im Terminate-Prozess (nachdem der Request beantwortet wurde).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class AnalyticsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ConfigInterface $config,
        private SessionManager $sessionManager,
    ) {
    }

    public function process(ServerRequest $request, callable $next): mixed
    {
        $response = $next($request);

        try {
            $this->trackEvent($request);
        } catch (\Throwable) {
        }

        return $response;
    }

    private function trackEvent(ServerRequest $request): void
    {
        if ($this->config->get('is_local_env', false)) {
            return;
        }
        $scriptName = $request->server['SCRIPT_NAME'] ?? '';
        if (\str_contains($scriptName, '/api/') || \str_contains($scriptName, 'cron.php') || \str_contains($scriptName, 'process_mail_queue.php')) {
            return;
        }

        $gaCfg     = $this->config->get('ga4_server_side', []);
        $gaId      = $gaCfg['measurement_id'] ?? '';
        $apiSecret = $gaCfg['api_secret'] ?? '';
        if ($gaId === '' || $apiSecret === '') {
            return;
        }

        if ($this->sessionManager->getAnalyticsId() === null) {
            $this->sessionManager->setAnalyticsId(\bin2hex(\random_bytes(16)));
        }

        $baseUrl = $this->config->getBaseUrl() !== ''
            ? \rtrim($this->config->getBaseUrl(), '/')
            : 'https://' . ($request->server['SERVER_NAME'] ?? 'localhost');

        $pageLocation = $baseUrl . ($request->server['REQUEST_URI'] ?? '');
        $pageTitle    = \ucfirst(\basename($scriptName, '.php'));

        $payload = [
            'client_id' => $this->sessionManager->getAnalyticsId(),
            'events'    => [[
                'name'   => 'page_view',
                'params' => [
                    'page_location'        => $pageLocation,
                    'page_title'           => $pageTitle,
                    'engagement_time_msec' => 1,
                ],
            ]],
        ];

        $ch = \curl_init('https://www.google-analytics.com/mp/collect?measurement_id=' . \urlencode($gaId) . '&api_secret=' . \urlencode($apiSecret));
        if ($ch === false) {
            return;
        }
        \curl_setopt_array($ch, [
            \CURLOPT_PROTOCOLS      => \CURLPROTO_HTTPS,
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_POST           => true,
            \CURLOPT_POSTFIELDS     => \json_encode($payload),
            \CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            \CURLOPT_TIMEOUT_MS     => 250,
        ]);
        \curl_exec($ch);
        // TODO \curl_close($ch); ggf. entfernen, da deprecated
        \curl_close($ch);
    }
}
