<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Contracts\MiddlewareInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Security\RateLimiterInterface;
use Override;

/**
 * Middleware zum Schutz vor Brute-Force-Angriffen (Rate Limiting).
 */
final readonly class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private RateLimiterInterface $rateLimiter,
        private SessionManager $sessionManager,
        private string $fallbackUrl,
    ) {
    }

    #[Override]
    public function process(ServerRequest $request, callable $next): ResponseInterface
    {
        $ip = $request->getIp();

        if ($this->rateLimiter->isBlocked($ip)) {
            $this->sessionManager->addFlash('error', 'Zu viele Versuche. Die IP-Adresse wurde für 15 Minuten gesperrt.');
            // Hängt die Parameter sauber an die URL an
            $separator = \str_contains($this->fallbackUrl, '?') ? '&' : '?';

            return new RedirectResponse($this->fallbackUrl . $separator . 'sent=0');
        }

        return $next($request);
    }
}
