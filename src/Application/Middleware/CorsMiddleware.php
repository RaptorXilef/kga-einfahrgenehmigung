<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Contracts\MiddlewareInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\EmptyResponse;
use Override;

/**
 * Fängt CORS Pre-Flight Requests ab, bevor sie die Anwendung belasten.
 */
final readonly class CorsMiddleware implements MiddlewareInterface
{
    #[Override]
    public function process(ServerRequest $request, callable $next): ResponseInterface
    {
        if ($request->getMethod() === 'OPTIONS') {
            return new EmptyResponse(204);
        }

        return $next($request);
    }
}
