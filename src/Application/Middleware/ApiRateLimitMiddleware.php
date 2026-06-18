<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Response\JsonResponse;
use App\Contracts\Application\MiddlewareInterface;
use App\Contracts\Security\RateLimiterInterface;

/**
 * TODO DOCBLOCK
 *
 * Path: src/Application/Middleware/ApiRateLimitMiddleware.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
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
