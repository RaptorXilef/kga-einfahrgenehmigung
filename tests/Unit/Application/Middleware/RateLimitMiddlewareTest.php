<?php

declare(strict_types=1);

use App\Application\Http\ServerRequest;
use App\Application\Middleware\RateLimitMiddleware;
use App\Application\Response\HtmlResponse;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Security\RateLimiterInterface;

\covers(RateLimitMiddleware::class);

\test('it passes the request to the next layer if IP is not blocked', function (): void {
    /** @var RateLimiterInterface&\PHPUnit\Framework\MockObject\Stub $limiter */
    $limiter = $this->createStub(RateLimiterInterface::class);
    $limiter->method('isBlocked')->willReturn(false);

    /** @var SessionManager&\PHPUnit\Framework\MockObject\Stub $session */
    $session = $this->createStub(SessionManager::class);

    $middleware = new RateLimitMiddleware($limiter, $session, '/fallback');
    $request = new ServerRequest(server: ['REMOTE_ADDR' => '127.0.0.1']);

    $response = $middleware->process($request, function ($req) {
        return new HtmlResponse('Success');
    });

    \expect($response)->toBeInstanceOf(HtmlResponse::class);
});

\test('it halts the request and redirects if IP is blocked', function (): void {
    /** @var RateLimiterInterface&\PHPUnit\Framework\MockObject\Stub $limiter */
    $limiter = $this->createStub(RateLimiterInterface::class);
    $limiter->method('isBlocked')->willReturn(true); // SIMULIERE SPERRE

    /** @var SessionManager&\PHPUnit\Framework\MockObject\MockObject $session */
    $session = $this->createMock(SessionManager::class);

    // Es MUSS eine Flash-Message gesetzt werden
    $session->expects($this->once())->method('addFlash');

    $middleware = new RateLimitMiddleware($limiter, $session, '/fallback');
    $request = new ServerRequest(server: ['REMOTE_ADDR' => '127.0.0.1']);

    // Die Action (Next-Closure) darf niemals aufgerufen werden
    $next = function () {
        throw new \Exception('Sollte niemals erreicht werden!');
    };

    $response = $middleware->process($request, $next);

    // Wir erwarten einen Rauswurf (Redirect)
    \expect($response)->toBeInstanceOf(RedirectResponse::class)
        ->and($response->url)->toBe('/fallback?sent=0');
});
