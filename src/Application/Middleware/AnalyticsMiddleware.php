<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Contracts\MiddlewareInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Http\ServerRequest;
use App\Application\Session\SessionManager;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\System\AnalyticsTrackerInterface;
use App\Contracts\Utils\ClockInterface;
use Override;
use Throwable;

/**
 * Sendet serverseitige Events an Google Analytics (GA4).
 * Asynchron im Terminate-Prozess (nachdem der Request beantwortet wurde).
 */
final readonly class AnalyticsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ConfigInterface $config,
        private SessionManager $sessionManager,
        private ClockInterface $clock,
        private AnalyticsTrackerInterface $analyticsTracker,
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
        if (!\is_string($consentCookie) || $consentCookie === '') {
            return; // Kein Consent-Cookie vorhanden -> Nichts tracken
        }

        $consent = \json_decode($consentCookie, true);
        if (!\is_array($consent) || !isset($consent['analytics']) || (bool) $consent['analytics'] === false) {
            return; // Nutzer hat Analytics abgelehnt -> Nichts tracken
        }

        $gaCfg = $this->config->getArray('ga4_server_side');
        if (($gaCfg['measurement_id'] ?? '') === '' || ($gaCfg['api_secret'] ?? '') === '') {
            return;
        }

        $clientId = $this->sessionManager->getAnalyticsId();
        if ($clientId === null) {
            $clientId = \bin2hex(\random_bytes(16));
            $this->sessionManager->setAnalyticsId($clientId);
        }

        // --- 2. Strict Session Manager anstelle von $_SESSION ---
        $sessionId = $this->sessionManager->getAnalyticsSessionId();
        if ($sessionId === null) {
            $sessionId = $this->clock->now()->getTimestamp();
            $this->sessionManager->setAnalyticsSessionId($sessionId);
        }

        $serverName = \is_string($request->server['SERVER_NAME'] ?? null) ? $request->server['SERVER_NAME'] : 'localhost';
        $baseUrl = $this->config->getBaseUrl() !== ''
            ? \rtrim($this->config->getBaseUrl(), '/')
            : 'https://' . $serverName;

        $pageLocation = $baseUrl . $path;

        // Titel dynamisch anhand des Pfads bauen
        $cleanPath = \trim($path, '/');
        $pageTitle = $cleanPath === '' ? 'Home' : \ucfirst($cleanPath);

        $this->analyticsTracker->trackPageView($clientId, $sessionId, $pageLocation, $pageTitle);
    }
}
