<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Contracts\MiddlewareInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Contracts\Security\AuthorizationInterface;
use Override;

final readonly class PermissionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthorizationInterface $auth,
        private string $requiredPermission,
        private string $fallbackUrl,
    ) {
    }

    #[Override]
    public function process(ServerRequest $request, callable $next): ResponseInterface
    {
        if (!$this->auth->hasPermission($this->requiredPermission)) {
            return new RedirectResponse($this->fallbackUrl);
        }

        return $next($request);
    }
}
