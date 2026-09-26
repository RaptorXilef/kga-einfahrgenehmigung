<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Contracts\MiddlewareInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Security\AuthorizationInterface;
use Override;

/**
 * Prüft, ob eine gültige Benutzersitzung vorliegt, synchronisiert Rolle sowie Berechtigungen
 * live gegen die Datenbank und leitet andernfalls auf die passende Login-Maske weiter.
 */
final readonly class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private SessionManager $sessionManager,
        private ConfigInterface $config,
        private AuthorizationInterface $auth,
    ) {
    }

    #[Override]
    public function process(ServerRequest $request, callable $next): ResponseInterface
    {
        $path = $request->getPath();
        $baseUrl = \rtrim($this->config->getBaseUrl(), '/');

        // Für Calls im Frontend-History-Bereich
        if (\str_starts_with($path, '/history')) {
            if ($this->sessionManager->getUserId() === '') {
                return new RedirectResponse($baseUrl . '/history_login');
            }

            return $next($request);
        }

        // Validiert die Admin-Sitzung gegen die Datenbank und aktualisiert Rolle + Berechtigungen in Echtzeit
        if (!$this->auth->isLoggedIn()) {
            // Optionalen Prüf-Code bei Weiterleitung auf den Admin-Login erhalten
            $code = \trim((string) ($request->get['code'] ?? ''));
            $querySuffix = $code !== '' ? '?code=' . \urlencode($code) : '';

            return new RedirectResponse($baseUrl . '/admin_login' . $querySuffix);
        }

        return $next($request);
    }
}
