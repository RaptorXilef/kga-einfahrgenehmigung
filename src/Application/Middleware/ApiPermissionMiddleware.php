<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Response\JsonResponse;
use App\Contracts\Application\MiddlewareInterface;
use App\Core\Service\AuthService;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class ApiPermissionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthService $auth,
        private string $permission,
    ) {
    }

    public function process(array $requestData, callable $next): mixed
    {
        if (! $this->auth->isLoggedIn() || ! $this->auth->hasPermission($this->permission)) {
            return JsonResponse::error('Nicht autorisiert. Es fehlen die Rechte.', 403);
        }

        return $next($requestData);
    }
}
