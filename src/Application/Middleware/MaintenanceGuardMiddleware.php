<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Http\ServerRequest;
use App\Contracts\Application\MiddlewareInterface;
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

        $currentScript = \basename((string) ($request->server['SCRIPT_NAME'] ?? ''));
        if ($currentScript === 'maintenance.php') {
            return $next($request);
        }

        $adminMaintenance      = $this->config->get('maintenance_mode_admin', false) === true;
        $publicMaintenance     = $this->config->get('maintenance_mode', false) === true;
        $shouldShowMaintenance = false;

        if ($adminMaintenance) {
            $shouldShowMaintenance = true;
        } elseif ($publicMaintenance) {
            $allowedAdminScripts = ['admin.php', 'users.php'];
            if (! \in_array($currentScript, $allowedAdminScripts, true) && ! \str_contains((string) ($request->server['SCRIPT_NAME'] ?? ''), '/api/')) {
                $shouldShowMaintenance = true;
            }
        }

        if ($shouldShowMaintenance) {
            \http_response_code(503);
            \header('Retry-After: 3600');

            $appRoot  = $this->config->get('root_path');
            $settings = [
                'base_url'               => $this->config->getBaseUrl(),
                'vereins_name'           => $this->config->get('vereins_name'),
                'maintenance_mode_admin' => $adminMaintenance,
            ];

            require $appRoot . '/public/maintenance.php';
            exit;
        }

        return $next($request);
    }
}
