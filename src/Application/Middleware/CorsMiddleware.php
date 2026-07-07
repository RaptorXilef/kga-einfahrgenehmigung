<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Contracts\MiddlewareInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\EmptyResponse;

/**
 * Fängt CORS Pre-Flight Requests ab, bevor sie die Anwendung belasten.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class CorsMiddleware implements MiddlewareInterface
{
    public function process(ServerRequest $request, callable $next): mixed
    {
        if (($request->getMethod() ?? '') === 'OPTIONS') {
            return new EmptyResponse(204);
        }

        return $next($request);
    }
}
