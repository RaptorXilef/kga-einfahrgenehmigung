<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Response\EmptyResponse;
use App\Contracts\Application\MiddlewareInterface;

/**
 * Fängt CORS Pre-Flight Requests ab, bevor sie die Anwendung belasten.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class CorsMiddleware implements MiddlewareInterface
{
    public function process(array $requestData, callable $next): mixed
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            return new EmptyResponse(204);
        }

        return $next($requestData);
    }
}
