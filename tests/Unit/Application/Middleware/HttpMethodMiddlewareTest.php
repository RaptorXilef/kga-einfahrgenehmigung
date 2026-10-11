<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Middleware;

use App\Application\Http\ServerRequest;
use App\Application\Middleware\HttpMethodMiddleware;
use App\Application\Response\HtmlResponse;
use App\Application\Response\JsonResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(HttpMethodMiddleware::class)]
final class HttpMethodMiddlewareTest extends TestCase
{
    #[Test]
    public function itBlocksDisallowedMethodsWithJsonResponse(): void
    {
        $middleware = new HttpMethodMiddleware(['POST']);
        $request = new ServerRequest(server: ['REQUEST_METHOD' => 'GET']);

        /** @var JsonResponse $response */
        $response = $middleware->process($request, fn () => new HtmlResponse('OK'));

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(405, $response->statusCode);
    }

    #[Test]
    public function itPassesAllowedMethods(): void
    {
        $middleware = new HttpMethodMiddleware(['POST']);
        $request = new ServerRequest(server: ['REQUEST_METHOD' => 'POST']);

        $response = $middleware->process($request, fn () => new HtmlResponse('OK'));

        $this->assertInstanceOf(HtmlResponse::class, $response);
    }
}
