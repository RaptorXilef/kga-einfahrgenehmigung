<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Middleware;

use App\Application\Http\ServerRequest;
use App\Application\Middleware\RateLimitMiddleware;
use App\Application\Response\HtmlResponse;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Security\RateLimiterInterface;
use App\SharedKernel\Infrastructure\Utils\SystemClock;
use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RateLimitMiddleware::class)]
final class RateLimitMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (\session_status() === \PHP_SESSION_NONE) {
            \session_start();
        }
        $_SESSION = [];
    }

    #[Test]
    public function itPassesTheRequestIfIpIsNotBlocked(): void
    {
        $limiter = $this->createStub(RateLimiterInterface::class);
        $limiter->method('isBlocked')->willReturn(false);

        $session = new SessionManager(new SystemClock());
        $middleware = new RateLimitMiddleware($limiter, $session, '/fallback');
        $request = new ServerRequest(server: ['REMOTE_ADDR' => '127.0.0.1']);

        $response = $middleware->process($request, fn (): HtmlResponse => new HtmlResponse('Success'));
        $this->assertInstanceOf(HtmlResponse::class, $response);
    }

    #[Test]
    public function itHaltsAndRedirectsIfIpIsBlocked(): void
    {
        $limiter = $this->createStub(RateLimiterInterface::class);
        $limiter->method('isBlocked')->willReturn(true);

        $session = new SessionManager(new SystemClock());
        $middleware = new RateLimitMiddleware($limiter, $session, '/fallback');
        $request = new ServerRequest(server: ['REMOTE_ADDR' => '127.0.0.1']);

        $next = fn () => throw new Exception('Sollte niemals erreicht werden!');
        $response = $middleware->process($request, $next);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/fallback?sent=0', $response->url);
        $this->assertArrayHasKey('error', $session->getFlashes());
    }
}
