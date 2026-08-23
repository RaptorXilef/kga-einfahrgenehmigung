<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Actions\AdminLoginAction;
use App\Application\Actions\SystemCronAction;
use App\Application\Actions\SystemProcessMailQueueAction;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Middleware\AuthMiddleware;
use App\Application\Middleware\MiddlewarePipeline;
use App\Application\Middleware\SecurityHeadersMiddleware;
use App\Application\Response\HtmlResponse;
use App\Application\Response\JsonResponse;
use App\Application\Routing\UniversalActionFactory;
use App\Application\Session\SessionManager;
use App\Contracts\Config\ConfigInterface;

/**
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
final readonly class FrontendController
{
    public function __construct(
        private ConfigInterface $config,
        private UniversalActionFactory $actionFactory,
        private SecurityHeadersMiddleware $securityHeaders,
        private SessionManager $sessionManager,
    ) {
    }

    public function handleRequest(ServerRequest $request): ?ResponseInterface
    {
        $relativePath = $this->resolveRelativePath($request);
        $routeMatch = $this->resolveRoute($request, $relativePath);

        $request = $routeMatch['request'];
        $className = $routeMatch['class'];
        $requiresAuth = $routeMatch['requiresAuth'];

        if ($this->isMaintenanceLockActive($className)) {
            return $this->sendMaintenanceResponse($className);
        }

        return $this->executePipeline($request, $className, $requiresAuth);
    }

    private function resolveRelativePath(ServerRequest $request): string
    {
        $pathRaw = \parse_url($request->getPath(), \PHP_URL_PATH);
        $path = \is_string($pathRaw) ? $pathRaw : '/';

        $basePathRaw = \parse_url($this->config->getBaseUrl(), \PHP_URL_PATH);
        $basePath = \is_string($basePathRaw) ? $basePathRaw : '/';

        $relativePath = '/' . \ltrim($path, '/');

        // Wenn die App in einem Unterordner läuft
        if (\str_starts_with($path, $basePath)) {
            $relativePath = '/' . \ltrim(\substr($path, \strlen($basePath)), '/');
        }

        // KGA Legacy Support: public/admin.php -> /admin
        if (\str_ends_with($relativePath, '.php')) {
            $relativePath = \substr($relativePath, 0, -4);
        }

        if ($relativePath === '/index') {
            return '/';
        }

        return $relativePath;
    }

    /**
     * @return array{request: ServerRequest, class: string, requiresAuth: bool}
     */
    private function resolveRoute(ServerRequest $request, string $relativePath): array
    {
        $method = $request->getMethod();
        $matched = $this->actionFactory->getRegistry()->match($method, $relativePath);

        // Fallback, wenn Route nicht gefunden
        if ($matched === null) {
            // Entweder du hast eine 404 Action, oder wir geben hier null zurück und handeln es in der Pipeline
            return [
                'request' => $request,
                'class' => '',
                'requiresAuth' => false,
            ];
        }

        if (\is_array($matched)) {
            $className = \is_string($matched['class']) ? $matched['class'] : '';
            $params = \is_array($matched['params']) ? $matched['params'] : [];

            return [
                'request' => $request->withInput(\array_merge($request->input, $params)),
                'class' => $className,
                'requiresAuth' => ($matched['requiresAuth'] ?? false) === true,
            ];
        }

        return [
            'request' => $request,
            'class' => '',
            'requiresAuth' => false,
        ];
    }

    private function isMaintenanceLockActive(string $className): bool
    {
        $maintenanceMode = $this->config->get('maintenance_mode', false) === true;
        $maintenanceAdmin = $this->config->get('maintenance_mode_admin', false) === true;

        $isAdminAction = \str_contains($className, '\\Admin');
        $isFrontendAction = !$isAdminAction;

        $safeDuringMaintenance = [
            AdminLoginAction::class,
            SystemCronAction::class,
            SystemProcessMailQueueAction::class,
        ];

        if (\in_array($className, $safeDuringMaintenance, true)) {
            return false;
        }

        if ($isAdminAction && $maintenanceAdmin) {
            return true;
        }

        return $isFrontendAction && $maintenanceMode && $this->sessionManager->getAdminGroup() !== 'admin';
    }

    private function sendMaintenanceResponse(string $className): ResponseInterface
    {
        if (\str_contains($className, '\\Api')) {
            return JsonResponse::error('System wird gewartet.', 503);
        }

        \ob_start();
        $rootPathRaw = $this->config->get('root_path');
        $rootPath = \is_string($rootPathRaw) ? $rootPathRaw : '';
        require_once \rtrim($rootPath, '/\\') . '/public/maintenance.php';
        $html = \ob_get_clean();

        return new HtmlResponse((string) $html, 503);
    }

    private function executePipeline(ServerRequest $request, string $className, bool $requiresAuth): ?ResponseInterface
    {
        $pipeline = new MiddlewarePipeline();

        $pipeline->add($this->securityHeaders);

        if ($requiresAuth) {
            $pipeline->add(new AuthMiddleware($this->sessionManager, $this->config));
        }

        $response = $pipeline->process($request, function (ServerRequest $req) use ($className): mixed {

            $action = $this->actionFactory->create($className);

            if ($action instanceof ActionInterface || $action instanceof ViewActionInterface) {
                return $action->execute($req);
            }

            return new HtmlResponse('404 Not Found - Route oder Klasse nicht konfiguriert.', 404);
        });

        if ($response instanceof ResponseInterface) {
            return $response;
        }

        return null;
    }
}
