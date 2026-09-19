<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Contracts\MiddlewareInterface;
use App\Application\Http\ServerRequest;
use App\Contracts\Config\ConfigInterface;

/**
 * Fallback Middleware für System-Sperren.
 * Nutzt die selbe konfigurierte Logik wie der FrontendController.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class MaintenanceGuardMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ConfigInterface $config,
    ) {
    }

    public function process(ServerRequest $request, callable $next): mixed
    {
        if (\php_sapi_name() === 'cli') {
            return $next($request);
        }

        $path = $request->getPath();
        if ($path === '/maintenance') {
            return $next($request);
        }

        $mConfig = $this->config->get('maintenance', []);

        $frontendGlobal = $mConfig['frontend'] ?? $this->config->get('maintenance_mode', false);
        $adminGlobal = $mConfig['admin'] ?? $this->config->get('maintenance_mode_admin', false);
        $globalMsg = $mConfig['message'] ?? 'Wir aktualisieren gerade das System, um Ihnen den bestmöglichen Service zu bieten.';
        $routeRules = $mConfig['routes'] ?? [];

        $isFrontendRoute = \in_array($path, ['/', '/check', '/checkout', '/history', '/success', '/verify', '/datenschutz', '/impressum'], true);
        $shouldShowMaintenance = false;
        $message = $globalMsg;

        if (isset($routeRules[$path]) && $routeRules[$path] !== false) {
            $shouldShowMaintenance = true;
            if (\is_string($routeRules[$path])) {
                $message = $routeRules[$path];
            }
        }

        if (!$shouldShowMaintenance) {
            if ($adminGlobal) {
                $shouldShowMaintenance = true;
            } elseif ($frontendGlobal && $isFrontendRoute) {
                $shouldShowMaintenance = true;
            }
        }

        if ($shouldShowMaintenance && !\str_contains($path, '/api/')) {
            \http_response_code(503);
            \header('Retry-After: 3600');

            $appRoot = $this->config->get('root_path');
            $settings = [
                'base_url' => $this->config->getBaseUrl(),
                'vereins_name' => $this->config->get('vereins_name'),
                'maintenance_mode_admin' => $adminGlobal,
                'maintenance_message' => $message,
            ];

            require $appRoot . '/public/maintenance.php';
            exit;
        }

        return $next($request);
    }
}
