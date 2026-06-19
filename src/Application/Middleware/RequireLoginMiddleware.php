<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Contracts\Application\MiddlewareInterface;
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

    // TODO DOCBLOCK
    public function process(array $requestData, callable $next): mixed
    {
        if (! $this->auth->isLoggedIn()) {
            \header("Location: {$this->fallbackUrl}");
            exit;
        }

        return $next($requestData);
    }
}
