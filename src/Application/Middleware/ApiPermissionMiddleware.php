<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Contracts\MiddlewareInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use App\Contracts\Security\AuthorizationInterface;
use Override;

final readonly class ApiPermissionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthorizationInterface $auth,
        private string $permission,
    ) {
    }

    #[Override]
    public function process(ServerRequest $request, callable $next): ResponseInterface
    {
        if (!$this->auth->isLoggedIn() || !$this->auth->hasPermission($this->permission)) {
            return JsonResponse::error('Nicht autorisiert. Es fehlen die Rechte.', 403);
        }

        return $next($request);
    }
}
