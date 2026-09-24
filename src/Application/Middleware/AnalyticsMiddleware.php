<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Contracts\MiddlewareInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Http\ServerRequest;
use App\Application\Session\SessionManager;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Utils\ClockInterface;
use Override;
use Throwable;

/**
 * Sendet Serverseitige Events an Google Analytics (GA4).
 * Asynchron im Terminate-Prozess (nachdem der Request beantwortet wurde).
 */
final readonly class AnalyticsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ConfigInterface $config,
        private SessionManager $sessionManager,
        private ClockInterface $clock,
    ) {
    }

    #[Override]
    public function process(ServerRequest $request, callable $next): ResponseInterface
    {
        $response = $next($request);

        try {
            $this->trackEvent($request);
        } catch (Throwable) {
        }

        return $response;
    }

    private function trackEvent(ServerRequest $request): void
    {
        if ($this->config->getBool('is_local_env', false)) {
            return;
        }

        $path = $request->getPath();
        if (\str_contains($path, '/api/') || \str_contains($path, 'cron') || \str_contains($path, 'process_mail_queue')) {
            return;
        }

        // --- 1. DATENSCHUTZ-FIX: Strict Request Wrapper anstelle von $_COOKIE ---
        $consentCookie = $request->cookie['kga_cookie_consent'] ?? null;
        if (!$consentCookie) {
            return; // Kein Consent-Cookie vorhanden -> Nichts tracken
        }

        $consent = \json_decode($consentCookie, true);
        if (empty($consent['analytics'])) {
            return; // Nutzer hat Analytics abgelehnt -> Nichts tracken
        }
        // -------------------------------------------

        $gaCfg = $this->config->getArray('ga4_server_side');
        $gaId = $gaCfg['measurement_id'] ?? '';
        $apiSecret = $gaCfg['api_secret'] ?? '';

        if ($gaId === '' || $apiSecret === '') {
            return;
        }

        if ($this->sessionManager->getAnalyticsId() === null) {
            $this->sessionManager->setAnalyticsId(\bin2hex(\random_bytes(16)));
        }

        // --- 2. BUGFIX: Strict Session Manager anstelle von $_SESSION ---
        $sessionId = $this->sessionManager->getAnalyticsSessionId();
        if ($sessionId === null) {
            // Nutze die ClockInterface statt nativer time() Funktion für Testbarkeit!
            $sessionId = $this->clock->now()->getTimestamp();
            $this->sessionManager->setAnalyticsSessionId($sessionId);
        }
        // ---------------------------------

        $baseUrl = $this->config->getBaseUrl() !== ''
            ? \rtrim($this->config->getBaseUrl(), '/')
            : 'https://' . ($request->server['SERVER_NAME'] ?? 'localhost');

        $pageLocation = $baseUrl . $path;

        // Titel dynamisch anhand des Pfads bauen
        $cleanPath = \trim($path, '/');
        $pageTitle = $cleanPath === '' ? 'Home' : \ucfirst($cleanPath);

        $payload = [
            'client_id' => $this->sessionManager->getAnalyticsId(),
            'events' => [
                [
                    'name' => 'page_view',
                    'params' => [
                        'page_location' => $pageLocation,
                        'page_title' => $pageTitle,
                        'session_id' => $sessionId, // verknüpft die Klicks zu EINER Sitzung
                        'engagement_time_msec' => 1,
                    ],
                ],
            ],
        ];

        $ch = \curl_init('https://www.google-analytics.com/mp/collect?measurement_id=' . \urlencode($gaId) . '&api_secret=' . \urlencode($apiSecret));
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
