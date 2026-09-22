<?php

declare(strict_types=1);

namespace App\Application;

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
use App\Modules\Identity\Application\UseCases\AuthenticateAdmin\AdminLoginAction;

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

        if ($relativePath === '/maintenance') {
            return $this->sendMaintenanceResponse('ManualAccess', 'Manuelle Wartungsansicht aufgerufen.', $relativePath);
        }

        $routeMatch = $this->resolveRoute($request, $relativePath);

        $request = $routeMatch['request'];
        $className = $routeMatch['class'];
        $requiresAuth = $routeMatch['requiresAuth'];

        // Wartungsmodus prüfen (Global + Granular)
        $maintenanceStatus = $this->checkMaintenanceStatus($className, $relativePath);
        if ($maintenanceStatus['active']) {
            return $this->sendMaintenanceResponse($className, $maintenanceStatus['message'], $relativePath);
        }

        return $this->executePipeline($request, $className, $requiresAuth);
    }

    /**
     * Prüft die Wartungsmodus-Einstellungen (Global und pro Route).
     *
     * @return array{active: bool, message: string}
     */
    private function checkMaintenanceStatus(string $className, string $relativePath): array
    {
        // Ausnahmeliste für essentielle Background-Prozesse, die selbst bei globaler Sperre laufen (optional anpassbar)
        $safeDuringMaintenance = [
            AdminLoginAction::class, // Login muss möglich sein, damit Admin ins Dashboard kommt
        ];

        if (\in_array($className, $safeDuringMaintenance, true)) {
            return ['active' => false, 'message' => ''];
        }

        $mConfig = $this->config->get('maintenance', []);

        $frontendGlobal = $mConfig['frontend'] ?? false;
        $adminGlobal = $mConfig['admin'] ?? false;
        $apiGlobal = $mConfig['api'] ?? false;
        $globalMsg = $mConfig['message'] ?? 'Wir aktualisieren gerade das System.';
        $routeRules = $mConfig['routes'] ?? [];

        $isApiRoute = \str_starts_with($relativePath, '/api/');
        $isAdminRoute = \in_array($relativePath, ['/admin', '/users', '/profile', '/changelog', '/admin_logout', '/admin_print'], true);
        $isFrontendRoute = !$isApiRoute && !$isAdminRoute;

        $isAdminLoggedIn = $this->sessionManager->getAdminGroup() === 'admin';

        $isActive = false;
        $message = $globalMsg;

        // 1. Feingranulare Prüfung pro Seite/Route
        if (isset($routeRules[$relativePath]) && $routeRules[$relativePath] !== false) {
            $isActive = true;
            if (\is_string($routeRules[$relativePath])) {
                $message = $routeRules[$relativePath];
            }
        }

        // 2. Globale Prüfung
        if (!$isActive) {
            if ($isApiRoute && $apiGlobal) {
                $isActive = true;
            } elseif ($isAdminRoute && $adminGlobal) {
                $isActive = true;
            } elseif ($isFrontendRoute && $frontendGlobal) {
                $isActive = true;
            }
        }

        // 3. Admin-Bypass: Administratoren dürfen das gesperrte Frontend zum Testen betreten
        // (API und Adminbereich bleiben für den Admin natürlich erreichbar, wenn sie nur global für User gesperrt sind)
        if ($isActive && $isFrontendRoute && $isAdminLoggedIn) {
            $isActive = false;
        }

        return ['active' => $isActive, 'message' => $message];
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
            return ['request' => $request, 'class' => '', 'requiresAuth' => false];
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

        return ['request' => $request, 'class' => '', 'requiresAuth' => false];
    }

    private function sendMaintenanceResponse(string $className, string $message, string $relativePath): ResponseInterface
    {
        // Wenn es eine API-Route ist, zwingend JSON mit 503 Status zurückgeben!
        if (\str_contains($className, '\\Api') || \str_starts_with($relativePath, '/api/')) {
            return JsonResponse::error($message, 503);
        }

        \ob_start();
        $rootPathRaw = $this->config->get('root_path', '');
        $rootPath = \is_string($rootPathRaw) ? $rootPathRaw : '';

        $settings = [
            'base_url' => \rtrim($this->config->getBaseUrl(), '/') . '/',
            'vereins_name' => $this->config->get('vereins_name', 'KGA e.V.'),
            'maintenance_mode_admin' => $this->config->get('maintenance', [])['admin'] ?? false,
            'maintenance_message' => $message,
        ];

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

            return new HtmlResponse('404 Not Found - Die angeforderte Seite existiert nicht.', 404);
        });

        if ($response instanceof ResponseInterface) {
            return $response;
        }

        return null;
    }
}
