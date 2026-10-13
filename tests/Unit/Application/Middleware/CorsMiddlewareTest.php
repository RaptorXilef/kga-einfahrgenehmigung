<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Middleware;

use App\Application\Http\ServerRequest;
use App\Application\Middleware\CorsMiddleware;
use App\Application\Response\EmptyResponse;
use App\Application\Response\HtmlResponse;
use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CorsMiddleware::class)]
final class CorsMiddlewareTest extends TestCase
{
    #[Test]
    public function itReturnsEmptyResponseOnOptionsRequest(): void
    {
        $middleware = new CorsMiddleware();
        $request = new ServerRequest(server: ['REQUEST_METHOD' => 'OPTIONS']);

        $next = fn () => throw new Exception('Sollte nicht aufgerufen werden');

        /** @var EmptyResponse $response */
        $response = $middleware->process($request, $next);

        $this->assertInstanceOf(EmptyResponse::class, $response);
        $this->assertSame(204, $response->status);
    }

    #[Test]
    public function itPassesThroughOtherMethods(): void
    {
        $middleware = new CorsMiddleware();
        $request = new ServerRequest(server: ['REQUEST_METHOD' => 'GET']);

        $response = $middleware->process($request, fn (): HtmlResponse => new HtmlResponse('OK'));

        $this->assertInstanceOf(HtmlResponse::class, $response);
    }
}
