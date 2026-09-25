<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Contracts\MiddlewareInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Config\ConfigInterface;
use Override;

/**
 * Prüft, ob eine gültige Benutzersitzung vorliegt, und leitet andernfalls auf die passende Login-Maske weiter.
 */
final readonly class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private SessionManager $sessionManager,
        private ConfigInterface $config,
    ) {
    }

    #[Override]
    public function process(ServerRequest $request, callable $next): ResponseInterface
    {
        if ($this->sessionManager->getUserId() === '') {
            $path = $request->getPath();
            $baseUrl = \rtrim($this->config->getBaseUrl(), '/');

            // Für Calls im Frontend-History-Bereich
            if (\str_starts_with($path, '/history')) {
                return new RedirectResponse($baseUrl . '/history_login');
            }

            // Optionalen Prüf-Code bei Weiterleitung auf den Admin-Login erhalten
            $code = \trim((string) ($request->get['code'] ?? ''));
            $querySuffix = $code !== '' ? '?code=' . \urlencode($code) : '';

            return new RedirectResponse($baseUrl . '/admin_login' . $querySuffix);
        }

        return $next($request);
    }
}
