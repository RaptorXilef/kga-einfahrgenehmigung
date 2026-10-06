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
    public function it_passes_the_request_if_ip_is_not_blocked(): void
    {
        $limiter = $this->createStub(RateLimiterInterface::class);
        $limiter->method('isBlocked')->willReturn(false);

        $session = new SessionManager(new SystemClock());
        $middleware = new RateLimitMiddleware($limiter, $session, '/fallback');
        $request = new ServerRequest(server: ['REMOTE_ADDR' => '127.0.0.1']);

        $response = $middleware->process($request, fn() => new HtmlResponse('Success'));
        self::assertInstanceOf(HtmlResponse::class, $response);
    }

    #[Test]
    public function it_halts_and_redirects_if_ip_is_blocked(): void
    {
        $limiter = $this->createStub(RateLimiterInterface::class);
        $limiter->method('isBlocked')->willReturn(true);

        $session = new SessionManager(new SystemClock());
        $middleware = new RateLimitMiddleware($limiter, $session, '/fallback');
        $request = new ServerRequest(server: ['REMOTE_ADDR' => '127.0.0.1']);

        $next = fn() => throw new \Exception('Sollte niemals erreicht werden!');
        $response = $middleware->process($request, $next);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/fallback?sent=0', $response->url);
        self::assertArrayHasKey('error', $session->getFlashes());
    }
}
