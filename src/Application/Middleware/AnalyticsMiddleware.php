<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Contracts\Application\MiddlewareInterface;
use App\Contracts\Config\ConfigInterface;

/**
 * Sendet Serverseitige Events an Google Analytics (GA4).
 * Asynchron im Terminate-Prozess (nachdem der Request beantwortet wurde).
 *
 * Path: src/Application/Middleware/AnalyticsMiddleware.php
 */
final readonly class AnalyticsMiddleware implements MiddlewareInterface
{
    public function __construct(private ConfigInterface $config)
    {
    }

    public function process(array $requestData, callable $next): mixed
    {
        // 1. Zuerst die Action ganz normal ausführen lassen
        $response = $next($requestData);

        // 2. Danach (Terminate-Phase) das Tracking lautlos absenden
        try {
            $this->trackEvent();
        } catch (\Throwable) {
            // Silently fail: Analytics darf die App nicht crashen
        }

        return $response;
    }

    private function trackEvent(): void
    {
        if ($this->config->get('is_local_env', false)) {
            return;
        }

        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';

        // Nicht bei APIs, Cronjobs oder internen Scripts tracken
        if (\str_contains($scriptName, '/api/') || \str_contains($scriptName, 'cron.php') || \str_contains($scriptName, 'process_mail_queue.php')) {
            return;
        }

        $gaCfg     = $this->config->get('ga4_server_side', []);
        $gaId      = $gaCfg['measurement_id'] ?? '';
        $apiSecret = $gaCfg['api_secret'] ?? '';

        if ($gaId === '' || $apiSecret === '') {
            return;
        }

        if (empty($_SESSION['ga4_client_id'])) {
            $_SESSION['ga4_client_id'] = \bin2hex(\random_bytes(16));
        }

        $baseUrl = $this->config->getBaseUrl() !== ''
            ? \rtrim($this->config->getBaseUrl(), '/')
            : 'https://' . ($_SERVER['SERVER_NAME'] ?? 'localhost');

        $pageLocation = $baseUrl . ($_SERVER['REQUEST_URI'] ?? '');
        $pageTitle    = \ucfirst(\basename($scriptName, '.php'));

        $payload = [
            'client_id' => $_SESSION['ga4_client_id'],
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

        \curl_setopt($ch, \CURLOPT_PROTOCOLS, \CURLPROTO_HTTPS);
        \curl_setopt($ch, \CURLOPT_REDIR_PROTOCOLS, \CURLPROTO_HTTPS);
        \curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($ch, \CURLOPT_POST, true);
        \curl_setopt($ch, \CURLOPT_POSTFIELDS, \json_encode($payload));
        \curl_setopt($ch, \CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        \curl_setopt($ch, \CURLOPT_TIMEOUT_MS, 250);

        \curl_exec($ch);
        \curl_close($ch);
    }
}
