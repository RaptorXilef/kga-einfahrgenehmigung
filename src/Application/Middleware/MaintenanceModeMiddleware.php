<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Contracts\MiddlewareInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\HtmlResponse;
use App\Application\Response\JsonResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Config\ConfigInterface;
use Override;

/**
 * Überwacht globale und feingranulare Wartungsmodi für die Anwendung.
 * Kapselt das Routing-Sicherheitsnetz sauber ab (SRP).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class MaintenanceModeMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ConfigInterface $config,
        private SessionManager $sessionManager,
    ) {
    }

    #[Override]
    public function process(ServerRequest $request, callable $next): ResponseInterface
    {
        $relativePath = $this->resolveRelativePath($request);

        if ($relativePath === '/maintenance') {
            return $this->sendMaintenanceResponse('Manuelle Wartungsansicht aufgerufen.', $relativePath);
        }

        $status = $this->checkMaintenanceStatus($relativePath);
        if ($status['active']) {
            return $this->sendMaintenanceResponse($status['message'], $relativePath);
        }

        return $next($request);
    }

    /**
     * @return array{active: bool, message: string}
     */
    private function checkMaintenanceStatus(string $relativePath): array
    {
        if ($relativePath === '/admin_login') {
            return ['active' => false, 'message' => ''];
        }

        $mConfig = $this->config->getArray('maintenance');

        $frontendGlobal = (bool) ($mConfig['frontend'] ?? false);
        $adminGlobal = (bool) ($mConfig['admin'] ?? false);
        $apiGlobal = (bool) ($mConfig['api'] ?? false);
        $globalMsg = (string) ($mConfig['message'] ?? 'Wir aktualisieren gerade das System.');
        $routeRules = (array) ($mConfig['routes'] ?? []);

        $isApiRoute = \str_starts_with($relativePath, '/api/');
        $isAdminRoute = \in_array($relativePath, ['/admin', '/users', '/profile', '/changelog', '/admin_logout', '/admin_print'], true);
        $isFrontendRoute = !$isApiRoute && !$isAdminRoute;

        $isAdminLoggedIn = $this->sessionManager->getAdminGroup() === 'admin';

        $isActive = false;
        $message = $globalMsg;

        if (isset($routeRules[$relativePath]) && $routeRules[$relativePath] !== false) {
            $isActive = true;
            if (\is_string($routeRules[$relativePath])) {
                $message = $routeRules[$relativePath];
            }
        }

        if (!$isActive) {
            if ($isApiRoute && $apiGlobal) {
                $isActive = true;
            } elseif ($isAdminRoute && $adminGlobal) {
                $isActive = true;
            } elseif ($isFrontendRoute && $frontendGlobal) {
                $isActive = true;
            }
        }

        // Admin-Bypass fürs Frontend
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

        if (\str_starts_with($path, $basePath)) {
            $relativePath = '/' . \ltrim(\substr($path, \strlen($basePath)), '/');
        }

        if (\str_ends_with($relativePath, '.php')) {
            $relativePath = \substr($relativePath, 0, -4);
        }

        if ($relativePath === '/index') {
            return '/';
        }

        return $relativePath;
    }

    private function sendMaintenanceResponse(string $message, string $relativePath): ResponseInterface
    {
        if (\str_starts_with($relativePath, '/api/')) {
            return JsonResponse::error($message, 503);
        }

        \ob_start();
        $rootPath = $this->config->getString('root_path');

        $settings = [
            'base_url' => \rtrim($this->config->getBaseUrl(), '/') . '/',
            'vereins_name' => $this->config->getString('vereins_name', 'KGA e.V.'),
            'maintenance_mode_admin' => $this->config->getArray('maintenance')['admin'] ?? false,
            'maintenance_message' => $message,
        ];

        require_once \rtrim($rootPath, '/\\') . '/public/maintenance.php';
        $html = \ob_get_clean();

        return new HtmlResponse((string) $html, 503);
    }
}
