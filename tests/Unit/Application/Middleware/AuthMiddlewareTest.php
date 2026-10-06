<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Middleware;

use App\Application\Http\ServerRequest;
use App\Application\Middleware\AuthMiddleware;
use App\Application\Response\HtmlResponse;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Security\AuthorizationInterface;
use App\SharedKernel\Infrastructure\Utils\SystemClock;
use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AuthMiddleware::class)]
final class AuthMiddlewareTest extends TestCase
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
    public function itAllowsAdminAccessIfUserIsProperlyLoggedIn(): void
    {
        $auth = $this->createStub(AuthorizationInterface::class);
        $auth->method('isLoggedIn')->willReturn(true);

        $session = new SessionManager(new SystemClock());
        $config = $this->createStub(ConfigInterface::class);
        $config->method('getBaseUrl')->willReturn('https://app.local/');

        $middleware = new AuthMiddleware($session, $config, $auth);
        $request = new ServerRequest(server: ['REQUEST_URI' => '/admin']);

        $response = $middleware->process($request, fn (): HtmlResponse => new HtmlResponse('Admin Area'));
        $this->assertInstanceOf(HtmlResponse::class, $response);
    }

    #[Test]
    public function itRedirectsToLoginIfUserIsNotLoggedIn(): void
    {
        $auth = $this->createStub(AuthorizationInterface::class);
        $auth->method('isLoggedIn')->willReturn(false);

        $session = new SessionManager(new SystemClock());
        $config = $this->createStub(ConfigInterface::class);
        $config->method('getBaseUrl')->willReturn('https://app.local/');

        $middleware = new AuthMiddleware($session, $config, $auth);

        // KILLT DEN MUTANTEN: Übergabe von Code mit Leerzeichen um das trim() zu erzwingen!
        $request = new ServerRequest(get: ['code' => '  XYZ  '], server: ['REQUEST_URI' => '/admin']);

        $next = fn () => throw new Exception('Sollte nicht passieren!');

        /** @var RedirectResponse $response */
        $response = $middleware->process($request, $next);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        // Erwarte, dass die Leerzeichen weggetrimmt wurden, bevor die URL gebaut wird
        $this->assertSame('https://app.local/admin_login?code=XYZ', $response->url);
    }
}
