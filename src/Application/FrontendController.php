<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Actions\Api\System\ArchiveCronAction;
use App\Application\Actions\Api\System\BackupCronAction;
use App\Application\Actions\Api\System\ProcessMailQueueAction;
use App\Application\Actions\Api\System\RemindersCronAction;
use App\Application\Actions\Api\System\SpamSyncCronAction;
use App\Application\Actions\Frontend\AdminLoginAction;
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

        // FIX: Ermöglicht den manuellen Aufruf der /maintenance URL
        if ($relativePath === '/maintenance') {
            return $this->sendMaintenanceResponse('ManualAccess', 'Manuelle Wartungsansicht aufgerufen.');
        }

        $routeMatch = $this->resolveRoute($request, $relativePath);

        $request = $routeMatch['request'];
        $className = $routeMatch['class'];
        $requiresAuth = $routeMatch['requiresAuth'];

        // Wartungsmodus prüfen (Global + Granular)
        $maintenanceStatus = $this->checkMaintenanceStatus($className, $relativePath);
        if ($maintenanceStatus['active']) {
            return $this->sendMaintenanceResponse($className, $maintenanceStatus['message']);
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
        $safeDuringMaintenance = [
            AdminLoginAction::class,
            ArchiveCronAction::class,
            BackupCronAction::class,
            ProcessMailQueueAction::class,
            RemindersCronAction::class,
            SpamSyncCronAction::class,
        ];

        if (\in_array($className, $safeDuringMaintenance, true)) {
            return ['active' => false, 'message' => ''];
        }

        $mConfig = $this->config->get('maintenance', []);

        // Kompatibilitäts-Fallback, falls die alten Keys noch irgendwo in einer local_config herumliegen
        $frontendGlobal = $mConfig['frontend'] ?? $this->config->get('maintenance_mode', false);
        $adminGlobal = $mConfig['admin'] ?? $this->config->get('maintenance_mode_admin', false);
        $globalMsg = $mConfig['message'] ?? 'Wir aktualisieren gerade das System, um Ihnen den bestmöglichen Service zu bieten.';
        $routeRules = $mConfig['routes'] ?? [];

        $isFrontendRoute = \in_array($relativePath, ['/', '/check', '/checkout', '/history', '/success', '/verify', '/datenschutz', '/impressum'], true);
        $isAdminLoggedIn = $this->sessionManager->getAdminGroup() === 'admin';

        $isActive = false;
        $message = $globalMsg;

        // 1. Feingranulare Prüfung pro Seite/Route
        if (isset($routeRules[$relativePath]) && $routeRules[$relativePath] !== false) {
            $isActive = true;
            if (\is_string($routeRules[$relativePath])) {
                $message = $routeRules[$relativePath]; // Spezifische Nachricht überschreibt globale Nachricht
            }
        }

        // 2. Globale Prüfung (greift, falls die Route nicht explizit geregelt ist)
        if (!$isActive) {
            if (!$isFrontendRoute && $adminGlobal) {
                $isActive = true;
            } elseif ($isFrontendRoute && $frontendGlobal) {
                $isActive = true;
            }
        }

        // 3. Admin-Bypass: Administratoren dürfen das gesperrte Frontend zum Testen betreten
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

    private function sendMaintenanceResponse(string $className, string $message): ResponseInterface
    {
        if (\str_contains($className, '\\Api')) {
            return JsonResponse::error('System wird gewartet.', 503);
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
