<?php

declare(strict_types=1);

namespace App\Modules\TestCtrl\Application\UseCases {
use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\HtmlResponse;

    #[Route('GET', '/secure-dashboard')]
    #[RequiresAuth]
    class SecureAction implements ActionInterface, RequiresPermissionInterface
    {
        public function getRequiredPermission(): string
        {
            return 'admin.access';
        }

        public function execute(ServerRequest $request): ResponseInterface
        {
            return new HtmlResponse('SECURE_OK');
        }
    }

    #[Route('GET', '/special-action')]
    #[RequiresAuth]
    class SpecialAction implements ActionInterface, RequiresPermissionInterface
    {
        public function getRequiredPermission(): string
        {
            return 'special.perm';
        }

        public function execute(ServerRequest $request): ResponseInterface
        {
            return new HtmlResponse('SPECIAL_OK');
        }
    }

    #[Route('GET', '/public-home')]
    class PublicAction implements ActionInterface
    {
        public function execute(ServerRequest $request): ResponseInterface
        {
            return new HtmlResponse('PUBLIC_OK');
        }
    }
}

namespace App\Tests\Unit\Application {
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

        protected function setUp(): void
        {
            parent::setUp();
            if (\session_status() === \PHP_SESSION_NONE) {
                \session_start();
            }
            $_SESSION = [];

            // Simuliere echtes Dateisystem für den Route-Scanner der ActionRegistry
            $this->tempRoot = \sys_get_temp_dir() . '/kga_fc_test_' . \uniqid();
            $dir = $this->tempRoot . '/src/Modules/TestCtrl/Application/UseCases';
            @\mkdir($dir, 0o777, true);

            // Wir legen nur leere Dateien ab, damit der Iterator sie findet.
            // Die eigentlichen Klassen sind bereits oben über die Namespace-Deklaration im RAM!
            \file_put_contents($dir . '/SecureAction.php', '<?php');
            \file_put_contents($dir . '/SpecialAction.php', '<?php');
            \file_put_contents($dir . '/PublicAction.php', '<?php');
        }

        protected function tearDown(): void
        {
            $dir = $this->tempRoot . '/src/Modules/TestCtrl/Application/UseCases';
            @\unlink($dir . '/SecureAction.php');
            @\unlink($dir . '/SpecialAction.php');
            @\unlink($dir . '/PublicAction.php');
            @\rmdir($dir);
            @\rmdir($this->tempRoot . '/src/Modules/TestCtrl/Application');
            @\rmdir($this->tempRoot . '/src/Modules/TestCtrl');
            @\rmdir($this->tempRoot . '/src/Modules');
            @\rmdir($this->tempRoot . '/src');
            @\rmdir($this->tempRoot);
            parent::tearDown();
        }

        private function createController(AuthorizationInterface $authService): FrontendController
        {
            $config = $this->createStub(ConfigInterface::class);
            $config->method('getBaseUrl')->willReturn('https://app.local/');
            // WICHTIG: Vermeidet TypeErrors durch strict return types und sauberes Match-Routing
            $config->method('getString')->willReturnCallback(fn (string $k, string $def = '') => $k === 'root_path' ? $this->tempRoot : $def);
            $config->method('getArray')->willReturnCallback(fn (string $k, array $def = []) => $def);
            $config->method('getBool')->willReturnCallback(fn (string $k, bool $def = false) => true);

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
}
