<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Contracts\MiddlewareInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Http\ServerRequest;
use App\Application\Session\SessionManager;
use Override;

/**
 * Global Security Headers.
 * Implementiert Zero-Trust CSP (Nonce-basiert), HSTS und Permissions-Policies zum Schutz vor XSS.
 */
final readonly class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function __construct(
        private SessionManager $sessionManager,
    ) {
    }

    #[Override]
    public function process(
        ServerRequest $request,
        callable $next,
    ): ResponseInterface {
        if (!\defined('CSP_NONCE')) {
            \define('CSP_NONCE', \rtrim(\base64_encode(\random_bytes(16)), '='));
        }

        if (!\headers_sent()) {
            // Verhindert das Caching der HTML-Seite durch den Browser. Zwingend nötig für korrekte
            // CSRF-Tokens und damit der Browser immer die neusten ?v= Datei-Versionen für CSS/JS lädt!
            \header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            \header('Pragma: no-cache');
            \header('Expires: 0');

            // Basis Security Header
            \header('X-Frame-Options: SAMEORIGIN');
            \header('X-Content-Type-Options: nosniff');
            // X-XSS-Protection entfernt. Gilt wohl als veraltet und kann Sicherheitslücken verursachen!
            \header('Referrer-Policy: strict-origin-when-cross-origin');
            \header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');

            // Saubere Nutzung des gekapselten ServerRequests anstelle von $_SERVER
            $hostRaw = $request->server['HTTP_HOST'] ?? '';
            $host = \is_string($hostRaw) ? $hostRaw : '';

            $isLocal = \str_ends_with($host, '.local')
                || $host === 'localhost'
                || $host === '127.0.0.1'
                || \php_sapi_name() === 'cli';

            $protocol = isset($request->server['HTTPS']) && $request->server['HTTPS'] === 'on' ? 'https://' : 'http://';

            $cspHeader = $this->buildCspHeader($isLocal, $host, $protocol);
            \header('Content-Security-Policy: ' . $cspHeader);

            // HSTS nur erzwingen, wenn wir NICHT in der lokalen Entwicklung sind
            if (!$isLocal) {
                \header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
            }
        }

        return $next($request);
    }

    private function buildCspHeader(bool $isLocal, string $host, string $protocol): string
    {
        // Hochsichere CSP Definition (Strict Nonce-Based)
        $csp = [
            'default-src' => ["'self'"],
            'upgrade-insecure-requests' => [],
            'script-src' => [
                "'self'",
                "'nonce-" . CSP_NONCE . "'",
                "'unsafe-eval'", // Nötig für Chart.js
                'https://cdnjs.cloudflare.com',
                'https://www.paypal.com',
                'https://www.sandbox.paypal.com',
                'https://www.googletagmanager.com',
            ],
            'style-src' => [
                "'self'",
                "'unsafe-inline'", // Bleibt aktiv, bis alle Inline-Styles ins SCSS gewandert sind
                'https://cdnjs.cloudflare.com',
            ],
            'font-src' => [
                "'self'",
                'data:',
                'https://cdnjs.cloudflare.com',
            ],
            'img-src' => [
                "'self'",
                'data:',
                'blob:',
                'https://api.qrserver.com',
                'https://www.google-analytics.com',
                'https://www.paypalobjects.com',
            ],
            'connect-src' => [
                "'self'",
                'https://*.google-analytics.com',
                'https://www.paypal.com',
                'https://www.sandbox.paypal.com',
                'https://cdnjs.cloudflare.com',
            ],
            'frame-src' => [
                "'self'",
                'https://www.paypal.com',
                'https://www.sandbox.paypal.com',
            ],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
        ];

        // Lokale Dev-Umgebungen dynamisch zu den Arrays hinzufügen
        if ($isLocal) {
            $localHosts = ['http://localhost'];
            if ($host !== '') {
                $localHosts[] = $protocol . $host;
            }

            foreach (['default-src', 'script-src', 'style-src', 'font-src', 'img-src', 'connect-src', 'frame-src'] as $directive) {
                $csp[$directive] = \array_merge($csp[$directive], $localHosts);
            }
        }

        // CSP Array zu einem sauberen String kompilieren
        $cspHeader = '';
        foreach ($csp as $directive => $sources) {
            $sourceString = $sources === [] ? '' : ' ' . \implode(' ', $sources);
            $cspHeader .= $directive . $sourceString . '; ';
        }

        return \trim($cspHeader);
    }
}
