<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application;

use App\Application\FrontendController;
use App\Application\Http\ServerRequest;
use App\Application\Middleware\AnalyticsMiddleware;
use App\Application\Middleware\ApiCsrfMiddleware;
use App\Application\Middleware\FormExceptionHandlerMiddleware;
use App\Application\Middleware\JsonBodyParserMiddleware;
use App\Application\Middleware\MaintenanceModeMiddleware;
use App\Application\Middleware\SecurityHeadersMiddleware;
use App\Application\Middleware\SystemMaintenanceMiddleware;
use App\Application\Response\HtmlResponse;
use App\Application\Response\RedirectResponse;
use App\Application\Routing\ActionRegistry;
use App\Application\Routing\UniversalActionFactory;
use App\Application\Session\SessionManager;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\DependencyInjection\ContainerInterface;
use App\Contracts\Security\AuthorizationInterface;
use App\Contracts\System\AnalyticsTrackerInterface;
use App\Contracts\System\AssetHelperInterface;
use App\Contracts\System\RouteCacheInterface;
use App\Contracts\System\StorageBootstrapperInterface;
use App\Contracts\System\SystemInfoInterface;
use App\SharedKernel\Infrastructure\Utils\SystemClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FrontendController::class)]
final class FrontendControllerTest extends TestCase
{
    private string $tempRoot;

    private string $secureClass;

    private string $specialClass;

    private string $publicClass;

    protected function setUp(): void
    {
        parent::setUp();
        if (\session_status() === \PHP_SESSION_NONE) {
            \session_start();
        }
        $_SESSION = [];

        $uniq = \uniqid();
        $this->secureClass = 'Secure' . $uniq;
        $this->specialClass = 'Special' . $uniq;
        $this->publicClass = 'Public' . $uniq;

        $sysTemp = \realpath(\sys_get_temp_dir()) ?: \sys_get_temp_dir();
        $this->tempRoot = $sysTemp . \DIRECTORY_SEPARATOR . 'kga_fc_test_' . $uniq;

        $dir = $this->tempRoot . \DIRECTORY_SEPARATOR . 'src' . \DIRECTORY_SEPARATOR . 'Modules' . \DIRECTORY_SEPARATOR . 'TestCtrl' . \DIRECTORY_SEPARATOR . 'Application' . \DIRECTORY_SEPARATOR . 'UseCases';
        @\mkdir($dir, 0o777, true);

        $secureCode = <<<PHP
            <?php
            namespace App\Modules\TestCtrl\Application\UseCases;
            use App\Application\Attribute\RequiresAuth;
            use App\Application\Attribute\Route;
            use App\Application\Contracts\ActionInterface;
            use App\Application\Contracts\RequiresPermissionInterface;
            use App\Application\Contracts\ResponseInterface;
            use App\Application\Http\ServerRequest;
            use App\Application\Response\HtmlResponse;

            #[Route('GET', '/secure-dashboard')]
            #[RequiresAuth]
            class {$this->secureClass}Action implements ActionInterface, RequiresPermissionInterface {
                public function getRequiredPermission(): string { return 'admin.access'; }
                public function execute(ServerRequest \$request): ResponseInterface { return new HtmlResponse('SECURE_OK'); }
            }
            PHP;

        $specialCode = <<<PHP
            <?php
            namespace App\Modules\TestCtrl\Application\UseCases;
            use App\Application\Attribute\RequiresAuth;
            use App\Application\Attribute\Route;
            use App\Application\Contracts\ActionInterface;
            use App\Application\Contracts\RequiresPermissionInterface;
            use App\Application\Contracts\ResponseInterface;
            use App\Application\Http\ServerRequest;
            use App\Application\Response\HtmlResponse;

            #[Route('GET', '/special-action')]
            #[RequiresAuth]
            class {$this->specialClass}Action implements ActionInterface, RequiresPermissionInterface {
                public function getRequiredPermission(): string { return 'special.perm'; }
                public function execute(ServerRequest \$request): ResponseInterface { return new HtmlResponse('SPECIAL_OK'); }
            }
            PHP;

        $publicCode = <<<PHP
            <?php
            namespace App\Modules\TestCtrl\Application\UseCases;
            use App\Application\Attribute\Route;
            use App\Application\Contracts\ActionInterface;
            use App\Application\Contracts\ResponseInterface;
            use App\Application\Http\ServerRequest;
            use App\Application\Response\HtmlResponse;

            #[Route('GET', '/public-home')]
            class {$this->publicClass}Action implements ActionInterface {
                public function execute(ServerRequest \$request): ResponseInterface { return new HtmlResponse('PUBLIC_OK'); }
            }
            PHP;

        \file_put_contents($dir . \DIRECTORY_SEPARATOR . $this->secureClass . 'Action.php', $secureCode);
        \file_put_contents($dir . \DIRECTORY_SEPARATOR . $this->specialClass . 'Action.php', $specialCode);
        \file_put_contents($dir . \DIRECTORY_SEPARATOR . $this->publicClass . 'Action.php', $publicCode);

        require_once $dir . \DIRECTORY_SEPARATOR . $this->secureClass . 'Action.php';
        require_once $dir . \DIRECTORY_SEPARATOR . $this->specialClass . 'Action.php';
        require_once $dir . \DIRECTORY_SEPARATOR . $this->publicClass . 'Action.php';
    }

    protected function tearDown(): void
    {
        $dir = $this->tempRoot . \DIRECTORY_SEPARATOR . 'src' . \DIRECTORY_SEPARATOR . 'Modules' . \DIRECTORY_SEPARATOR . 'TestCtrl' . \DIRECTORY_SEPARATOR . 'Application' . \DIRECTORY_SEPARATOR . 'UseCases';
        @\unlink($dir . \DIRECTORY_SEPARATOR . $this->secureClass . 'Action.php');
        @\unlink($dir . \DIRECTORY_SEPARATOR . $this->specialClass . 'Action.php');
        @\unlink($dir . \DIRECTORY_SEPARATOR . $this->publicClass . 'Action.php');
        @\rmdir($dir);
        @\rmdir($this->tempRoot . \DIRECTORY_SEPARATOR . 'src' . \DIRECTORY_SEPARATOR . 'Modules' . \DIRECTORY_SEPARATOR . 'TestCtrl' . \DIRECTORY_SEPARATOR . 'Application');
        @\rmdir($this->tempRoot . \DIRECTORY_SEPARATOR . 'src' . \DIRECTORY_SEPARATOR . 'Modules' . \DIRECTORY_SEPARATOR . 'TestCtrl');
        @\rmdir($this->tempRoot . \DIRECTORY_SEPARATOR . 'src' . \DIRECTORY_SEPARATOR . 'Modules');
        @\rmdir($this->tempRoot . \DIRECTORY_SEPARATOR . 'src');
        @\rmdir($this->tempRoot);
        parent::tearDown();
    }

    private function createController(AuthorizationInterface $authService): FrontendController
    {
        $config = $this->createStub(ConfigInterface::class);
        $config->method('getBaseUrl')->willReturn('https://app.local/');
        $config->method('getString')->willReturnCallback(fn (string $k, string $def = '') => $k === 'root_path' ? $this->tempRoot : $def);
        $config->method('getArray')->willReturnCallback(fn (string $k, array $def = []) => $def);
        $config->method('getBool')->willReturnCallback(fn (string $k, bool $def = false) => true);

        // Wir nutzen die echte Registry, welche die oben erzeugten Dummy-Klassen aus dem Temp-Verzeichnis lädt
        $registry = new ActionRegistry($config, $this->createStub(RouteCacheInterface::class));

        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturnCallback(fn (string $c) => new $c());

        $actionFactory = new UniversalActionFactory($registry, $container);
        $sessionManager = new SessionManager(new SystemClock());

        return new FrontendController(
            $config,
            $actionFactory,
            new SecurityHeadersMiddleware(),
            new SystemMaintenanceMiddleware($this->createStub(StorageBootstrapperInterface::class)),
            new AnalyticsMiddleware($config, $sessionManager, new SystemClock(), $this->createStub(AnalyticsTrackerInterface::class)),
            $sessionManager,
            $authService,
            new JsonBodyParserMiddleware(),
            new ApiCsrfMiddleware($sessionManager),
            new FormExceptionHandlerMiddleware($sessionManager),
            new MaintenanceModeMiddleware($config, $sessionManager, $this->createStub(AssetHelperInterface::class), $this->createStub(SystemInfoInterface::class), new SystemClock()),
        );
    }

    #[Test]
    public function itResolvesPublicRouteAndReturnsResponse(): void
    {
        $auth = $this->createStub(AuthorizationInterface::class);
        $controller = $this->createController($auth);

        $request = new ServerRequest(server: ['REQUEST_URI' => '/public-home']);
        /** @var HtmlResponse $response */
        $response = $controller->handleRequest($request);

        $this->assertInstanceOf(HtmlResponse::class, $response);
        $this->assertSame('PUBLIC_OK', $response->html);
    }

    #[Test]
    public function itRedirectsToLoginIfRouteRequiresAuthAndUserIsGuest(): void
    {
        $auth = $this->createStub(AuthorizationInterface::class);
        $auth->method('isLoggedIn')->willReturn(false);

        $controller = $this->createController($auth);

        $request = new ServerRequest(server: ['REQUEST_URI' => '/secure-dashboard']);
        /** @var RedirectResponse $response */
        $response = $controller->handleRequest($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('https://app.local/admin_login', $response->url);
    }

    #[Test]
    public function itReturns404IfRouteNotFound(): void
    {
        $auth = $this->createStub(AuthorizationInterface::class);
        $controller = $this->createController($auth);

        $request = new ServerRequest(server: ['REQUEST_URI' => '/does-not-exist']);
        /** @var HtmlResponse $response */
        $response = $controller->handleRequest($request);

        $this->assertInstanceOf(HtmlResponse::class, $response);
        $this->assertSame(404, $response->statusCode);
        $this->assertStringContainsString('404 Not Found', $response->html);
    }

    #[Test]
    public function itBlocksAccessAndLogsOutIfUserLacksBaseAdminPermission(): void
    {
        $auth = $this->createMock(AuthorizationInterface::class);
        $auth->method('isLoggedIn')->willReturn(true);
        $auth->method('hasPermission')->willReturn(false);

        $auth->expects($this->once())->method('logout');

        $controller = $this->createController($auth);

        $request = new ServerRequest(server: ['REQUEST_URI' => '/secure-dashboard']);
        /** @var RedirectResponse $response */
        $response = $controller->handleRequest($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('https://app.local/admin_login', $response->url);
    }

    #[Test]
    public function itBlocksAccessAndRedirectsToAdminIfUserHasBasePermButLacksSpecificPerm(): void
    {
        $auth = $this->createMock(AuthorizationInterface::class);
        $auth->method('isLoggedIn')->willReturn(true);
        $auth->method('hasPermission')->willReturnCallback(fn (string $perm) => $perm === 'admin.access');

        $auth->expects($this->never())->method('logout');

        $controller = $this->createController($auth);

        $request = new ServerRequest(server: ['REQUEST_URI' => '/special-action']);
        /** @var RedirectResponse $response */
        $response = $controller->handleRequest($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('https://app.local/admin', $response->url);
    }
}
