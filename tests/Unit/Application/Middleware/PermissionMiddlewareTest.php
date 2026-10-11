<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Middleware;

use App\Application\Http\ServerRequest;
use App\Application\Middleware\PermissionMiddleware;
use App\Application\Response\HtmlResponse;
use App\Application\Response\RedirectResponse;
use App\Contracts\Security\AuthorizationInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PermissionMiddleware::class)]
final class PermissionMiddlewareTest extends TestCase
{
    #[Test]
    public function itPassesIfUserHasPermission(): void
    {
        $auth = $this->createStub(AuthorizationInterface::class);
        $auth->method('hasPermission')->willReturn(true);

        $middleware = new PermissionMiddleware($auth, 'permits.create', '/fallback');
        $response = $middleware->process(new ServerRequest(), fn (): HtmlResponse => new HtmlResponse('OK'));

        $this->assertInstanceOf(HtmlResponse::class, $response);
    }

    #[Test]
    public function itRedirectsIfUserLacksPermission(): void
    {
        $auth = $this->createStub(AuthorizationInterface::class);
        $auth->method('hasPermission')->willReturn(false);

        $middleware = new PermissionMiddleware($auth, 'permits.create', '/fallback');
        $response = $middleware->process(new ServerRequest(), fn (): HtmlResponse => new HtmlResponse('OK'));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/fallback', $response->url);
    }
}
