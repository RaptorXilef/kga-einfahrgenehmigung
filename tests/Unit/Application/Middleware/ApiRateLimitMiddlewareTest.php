<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Middleware;

use App\Application\Http\ServerRequest;
use App\Application\Middleware\ApiRateLimitMiddleware;
use App\Application\Response\HtmlResponse;
use App\Application\Response\JsonResponse;
use App\Contracts\Security\RateLimiterInterface;
use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiRateLimitMiddleware::class)]
final class ApiRateLimitMiddlewareTest extends TestCase
{
    #[Test]
    public function itPassesRequestIfIpIsNotBlocked(): void
    {
        $rateLimiter = $this->createStub(RateLimiterInterface::class);
        $rateLimiter->method('isBlocked')->willReturn(false);

        $middleware = new ApiRateLimitMiddleware($rateLimiter);
        $request = new ServerRequest(server: ['REMOTE_ADDR' => '127.0.0.1']);

        $response = $middleware->process($request, fn (): HtmlResponse => new HtmlResponse('OK'));

        $this->assertInstanceOf(HtmlResponse::class, $response);
    }

    #[Test]
    public function itReturnsJsonErrorIfIpIsBlocked(): void
    {
        $rateLimiter = $this->createStub(RateLimiterInterface::class);
        $rateLimiter->method('isBlocked')->willReturn(true);

        $middleware = new ApiRateLimitMiddleware($rateLimiter);
        $request = new ServerRequest(server: ['REMOTE_ADDR' => '127.0.0.1']);

        $next = fn () => throw new Exception('Sollte nicht aufgerufen werden');

        /** @var JsonResponse $response */
        $response = $middleware->process($request, $next);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(429, $response->statusCode);
        $this->assertFalse($response->data['success']);
        $this->assertSame('Zu viele Anfragen. Bitte versuchen Sie es später erneut.', $response->data['error']);
    }
}
