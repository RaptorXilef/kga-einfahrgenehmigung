<?php

declare(strict_types=1);

use App\Application\Http\ServerRequest;
use App\Application\Middleware\RateLimitMiddleware;
use App\Application\Response\HtmlResponse;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Security\RateLimiterInterface;
use App\SharedKernel\Infrastructure\Utils\SystemClock;

covers(RateLimitMiddleware::class);

beforeEach(function (): void {
    if (\session_status() === \PHP_SESSION_NONE) {
        \session_start();
    }
    // Session für jeden Test sauber leeren (kein State-Bleed)
    $_SESSION = [];
});

test('it passes the request to the next layer if IP is not blocked', function (): void {
    /** @var RateLimiterInterface&\PHPUnit\Framework\MockObject\Stub $limiter */
    $limiter = $this->createStub(RateLimiterInterface::class);
    $limiter->method('isBlocked')->willReturn(false);

    // Echter SessionManager (State-based Testing) anstelle eines Mocks
    $session = new SessionManager(new SystemClock());

    $middleware = new RateLimitMiddleware($limiter, $session, '/fallback');
    $request = new ServerRequest(server: ['REMOTE_ADDR' => '127.0.0.1']);

    $response = $middleware->process($request, function ($req) {
        return new HtmlResponse('Success');
    });

    expect($response)->toBeInstanceOf(HtmlResponse::class);
});

test('it halts the request and redirects if IP is blocked', function (): void {
    /** @var RateLimiterInterface&\PHPUnit\Framework\MockObject\Stub $limiter */
    $limiter = $this->createStub(RateLimiterInterface::class);
    $limiter->method('isBlocked')->willReturn(true); // SIMULIERE SPERRE

    // Echter SessionManager fängt die Flash-Message ab
    $session = new SessionManager(new SystemClock());

    $middleware = new RateLimitMiddleware($limiter, $session, '/fallback');
    $request = new ServerRequest(server: ['REMOTE_ADDR' => '127.0.0.1']);

    // Die Action (Next-Closure) darf niemals aufgerufen werden
    $next = function (): void {
        throw new \Exception('Sollte niemals erreicht werden!');
    };

    /** @var RedirectResponse $response */
    $response = $middleware->process($request, $next);

    // Wir erwarten einen Rauswurf (Redirect) UND eine echte Flash-Message in der Session
    expect($response)->toBeInstanceOf(RedirectResponse::class)
        ->and($response->url)->toBe('/fallback?sent=0')
        ->and($session->getFlashes())->toHaveKey('error');
});
