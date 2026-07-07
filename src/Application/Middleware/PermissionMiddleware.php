<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Contracts\MiddlewareInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Core\Service\AuthService;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class PermissionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthService $auth,
        private string $requiredPermission,
        private string $fallbackUrl,
    ) {
    }

    public function process(ServerRequest $request, callable $next): mixed
    {
        if (! $this->auth->hasPermission($this->requiredPermission)) {
            return new RedirectResponse($this->fallbackUrl);
        }

        return $next($request);
    }
}
