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
    public function it_allows_admin_access_if_user_is_properly_logged_in(): void
    {
        $auth = $this->createStub(AuthorizationInterface::class);
        $auth->method('isLoggedIn')->willReturn(true);

        $session = new SessionManager(new SystemClock());
        $config = $this->createStub(ConfigInterface::class);
        $config->method('getBaseUrl')->willReturn('https://app.local/');

        $middleware = new AuthMiddleware($session, $config, $auth);
        $request = new ServerRequest(server: ['REQUEST_URI' => '/admin']);

        $response = $middleware->process($request, fn () => new HtmlResponse('Admin Area'));
        self::assertInstanceOf(HtmlResponse::class, $response);
    }

    #[Test]
    public function it_redirects_to_login_if_user_is_not_logged_in(): void
    {
        $auth = $this->createStub(AuthorizationInterface::class);
        $auth->method('isLoggedIn')->willReturn(false);

        $session = new SessionManager(new SystemClock());
        $config = $this->createStub(ConfigInterface::class);
        $config->method('getBaseUrl')->willReturn('https://app.local/');

        $middleware = new AuthMiddleware($session, $config, $auth);
        $request = new ServerRequest(server: ['REQUEST_URI' => '/admin'], get: ['code' => 'XYZ']);

        $next = fn () => throw new \Exception('Sollte nicht passieren!');
        $response = $middleware->process($request, $next);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('https://app.local/admin_login?code=XYZ', $response->url);
    }
}
