<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Response\JsonResponse;
use App\Contracts\Application\MiddlewareInterface;
use App\Contracts\Security\RateLimiterInterface;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class ApiRateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(private RateLimiterInterface $rateLimiter)
    {
    }

    public function process(array $requestData, callable $next): mixed
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        if ($this->rateLimiter->isBlocked($ip)) {
            JsonResponse::error('Zu viele Anfragen. Bitte versuchen Sie es später erneut.', 429);

            return null;
        }

        return $next($requestData);
    }
}
