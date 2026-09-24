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

            // Für API Calls im Backend
            if (\str_starts_with($path, '/api/') || \str_starts_with($path, '/admin') || \str_starts_with($path, '/users')) {
                return new RedirectResponse(\rtrim($this->config->getBaseUrl(), '/') . '/admin_login');
            }

            // Für API Calls im Frontend (z.B. History)
            if (\str_starts_with($path, '/history')) {
                return new RedirectResponse(\rtrim($this->config->getBaseUrl(), '/') . '/history_login');
            }

            return new RedirectResponse(\rtrim($this->config->getBaseUrl(), '/') . '/');
        }

        return $next($request);
    }
}
