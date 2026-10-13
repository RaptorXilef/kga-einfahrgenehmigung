<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Middleware;

use App\Application\Http\ServerRequest;
use App\Application\Middleware\MaintenanceModeMiddleware;
use App\Application\Response\HtmlResponse;
use App\Application\Response\JsonResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\System\AssetHelperInterface;
use App\Contracts\System\SystemInfoInterface;
use App\Contracts\Utils\ClockInterface;
use App\SharedKernel\Infrastructure\Utils\SystemClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MaintenanceModeMiddleware::class)]
final class MaintenanceModeMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (\session_status() === \PHP_SESSION_NONE) {
            \session_start();
        }
        $_SESSION = [];
    }

    private function getConfigStub(array $maintenanceConfig): ConfigInterface
    {
        $config = $this->createStub(ConfigInterface::class);
        $config->method('getArray')->willReturnMap([
            ['maintenance', [], $maintenanceConfig],
        ]);
        $config->method('getString')->willReturnMap([
            ['root_path', '', '/tmp'],
            ['vereins_name', 'KGA', 'KGA MOCK'],
        ]);
        $config->method('getBaseUrl')->willReturn('https://mock.de/');

        return $config;
    }

    #[Test]
    public function itPassesThroughIfMaintenanceIsDisabled(): void
    {
        $config = $this->getConfigStub(['frontend' => false, 'admin' => false, 'api' => false]);
        $middleware = new MaintenanceModeMiddleware(
            $config,
            new SessionManager(new SystemClock()),
            $this->createStub(AssetHelperInterface::class),
            $this->createStub(SystemInfoInterface::class),
            $this->createStub(ClockInterface::class),
        );

        $request = new ServerRequest(server: ['REQUEST_URI' => '/']);
        $response = $middleware->process($request, fn (): HtmlResponse => new HtmlResponse('OK'));

        $this->assertInstanceOf(HtmlResponse::class, $response);
        $this->assertSame('OK', $response->html);
    }

    #[Test]
    public function itAlwaysAllowsAdminLoginEvenIfGlobalAdminIsLocked(): void
    {
        $config = $this->getConfigStub(['admin' => true]);
        $middleware = new MaintenanceModeMiddleware(
            $config,
            new SessionManager(new SystemClock()),
            $this->createStub(AssetHelperInterface::class),
            $this->createStub(SystemInfoInterface::class),
            $this->createStub(ClockInterface::class),
        );

        $request = new ServerRequest(server: ['REQUEST_URI' => '/admin_login']);
        $response = $middleware->process($request, fn (): HtmlResponse => new HtmlResponse('LOGIN OK'));

        $this->assertSame('LOGIN OK', $response->html);
    }

    #[Test]
    public function itReturnsJsonResponseForApiRoutes(): void
    {
        $config = $this->getConfigStub(['api' => true, 'message' => 'API Pause']);
        $middleware = new MaintenanceModeMiddleware(
            $config,
            new SessionManager(new SystemClock()),
            $this->createStub(AssetHelperInterface::class),
            $this->createStub(SystemInfoInterface::class),
            $this->createStub(ClockInterface::class),
        );

        $request = new ServerRequest(server: ['REQUEST_URI' => '/api/ping']);
        /** @var JsonResponse $response */
        $response = $middleware->process($request, fn (): HtmlResponse => new HtmlResponse('OK'));

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(503, $response->statusCode);
        $this->assertSame('API Pause', $response->data['error']);
    }

    #[Test]
    public function itBypassesFrontendMaintenanceIfUserIsLoggedInAdmin(): void
    {
        $config = $this->getConfigStub(['frontend' => true]);

        $_SESSION['admin_group'] = 'admin'; // Echten Session-Status simulieren
        $session = new SessionManager(new SystemClock());

        $middleware = new MaintenanceModeMiddleware(
            $config,
            $session,
            $this->createStub(AssetHelperInterface::class),
            $this->createStub(SystemInfoInterface::class),
            $this->createStub(ClockInterface::class),
        );

        // Versucht das Frontend aufzurufen
        $request = new ServerRequest(server: ['REQUEST_URI' => '/']);
        $response = $middleware->process($request, fn (): HtmlResponse => new HtmlResponse('OK BYPASS'));

        $this->assertSame('OK BYPASS', $response->html);
    }

    #[Test]
    public function itReturnsZeroDependencyFallbackIfTemplateIsMissing(): void
    {
        $config = $this->getConfigStub(['frontend' => true, 'message' => 'Wartung aktiv!']);
        $session = new SessionManager(new SystemClock());

        $middleware = new MaintenanceModeMiddleware(
            $config,
            $session,
            $this->createStub(AssetHelperInterface::class),
            $this->createStub(SystemInfoInterface::class),
            $this->createStub(ClockInterface::class),
        );

        $request = new ServerRequest(server: ['REQUEST_URI' => '/']);

        // Da wir root_path auf /tmp gesetzt haben und es dort keine templates/pages/frontend/maintenance.phtml gibt,
        // muss der Zero-Dependency Fallback greifen!
        /** @var HtmlResponse $response */
        $response = $middleware->process($request, fn (): HtmlResponse => new HtmlResponse('OK'));

        $this->assertInstanceOf(HtmlResponse::class, $response);
        $this->assertSame(503, $response->statusCode);
        $this->assertStringContainsString('Wartung aktiv!', $response->html);
        $this->assertStringContainsString('KGA MOCK', $response->html);
        $this->assertStringContainsString('Kurze Pause!', $response->html);
    }
}
