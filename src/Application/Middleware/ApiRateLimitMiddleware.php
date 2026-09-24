<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Contracts\MiddlewareInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use App\Contracts\Security\RateLimiterInterface;
use Override;

final readonly class ApiRateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(private RateLimiterInterface $rateLimiter)
    {
    }

    #[Override]
    public function process(ServerRequest $request, callable $next): ResponseInterface
    {
        $ip = $request->getIp();

        if ($this->rateLimiter->isBlocked($ip)) {
            return JsonResponse::error('Zu viele Anfragen. Bitte versuchen Sie es später erneut.', 429);
        }

        return $next($request);
    }
}
