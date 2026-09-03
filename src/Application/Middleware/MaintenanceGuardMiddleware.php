<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Contracts\MiddlewareInterface;
use App\Application\Http\ServerRequest;
use App\Contracts\Config\ConfigInterface;

/**
 * TODO DOCBLOCK
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

        // FIX: Nutzt nun den Pfad anstelle von SCRIPT_NAME
        $path = $request->getPath();
        if ($path === '/maintenance') {
            return $next($request);
        }

        $adminMaintenance = $this->config->get('maintenance_mode_admin', false) === true;
        $publicMaintenance = $this->config->get('maintenance_mode', false) === true;
        $shouldShowMaintenance = false;

        if ($adminMaintenance) {
            $shouldShowMaintenance = true;
        } elseif ($publicMaintenance) {
            $allowedAdminScripts = ['/admin', '/users', '/profile'];
            if (!\in_array($path, $allowedAdminScripts, true) && !\str_contains($path, '/api/')) {
                $shouldShowMaintenance = true;
            }
        }

        if ($shouldShowMaintenance) {
            \http_response_code(503);
            \header('Retry-After: 3600');

            $appRoot = $this->config->get('root_path');
            $settings = [
                'base_url' => $this->config->getBaseUrl(),
                'vereins_name' => $this->config->get('vereins_name'),
                'maintenance_mode_admin' => $adminMaintenance,
            ];

            require $appRoot . '/public/maintenance.php';
            exit;
        }

        return $next($request);
    }
}
