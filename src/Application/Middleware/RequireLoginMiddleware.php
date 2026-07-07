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
final readonly class RequireLoginMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthService $auth,
        private string $fallbackUrl,
    ) {
    }

    public function process(ServerRequest $request, callable $next): mixed
    {
        if (! $this->auth->isLoggedIn()) {
            return new RedirectResponse($this->fallbackUrl);
        }

        return $next($request);
    }
}
