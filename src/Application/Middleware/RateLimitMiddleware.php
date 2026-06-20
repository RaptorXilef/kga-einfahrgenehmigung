<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Contracts\Application\MiddlewareInterface;
use App\Contracts\Security\RateLimiterInterface;

/**
 * Middleware zum Schutz vor Brute-Force-Angriffen (Rate Limiting).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private RateLimiterInterface $rateLimiter,
        private string $fallbackUrl,
    ) {
    }

    public function process(ServerRequest $request, callable $next): mixed
    {
        $ip = $request->getIp();

        if ($this->rateLimiter->isBlocked($ip)) {
            $msg = 'Zu viele Versuche. Die IP-Adresse wurde für 15 Minuten gesperrt.';

            // Hängt die Parameter sauber an die URL an
            $separator = \str_contains($this->fallbackUrl, '?') ? '&' : '?';

            return new RedirectResponse($this->fallbackUrl . $separator . 'sent=0&msg=' . \urlencode($msg));
        }

        return $next($request);
    }
}
