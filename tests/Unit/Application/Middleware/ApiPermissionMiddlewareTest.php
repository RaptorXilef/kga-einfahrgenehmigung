<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Middleware;

use App\Application\Http\ServerRequest;
use App\Application\Middleware\ApiPermissionMiddleware;
use App\Application\Response\HtmlResponse;
use App\Application\Response\JsonResponse;
use App\Contracts\Security\AuthorizationInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiPermissionMiddleware::class)]
final class ApiPermissionMiddlewareTest extends TestCase
{
    #[Test]
    public function itReturnsJsonErrorIfPermissionIsMissing(): void
    {
        $auth = $this->createStub(AuthorizationInterface::class);
        $auth->method('isLoggedIn')->willReturn(true);
        $auth->method('hasPermission')->willReturn(false);

        $middleware = new ApiPermissionMiddleware($auth, 'permits.create');
        /** @var JsonResponse $response */
        $response = $middleware->process(new ServerRequest(), fn () => new HtmlResponse('OK'));

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(403, $response->statusCode);
    }
}
