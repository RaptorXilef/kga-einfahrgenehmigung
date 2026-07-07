<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Contracts\MiddlewareInterface;
use App\Application\Http\ServerRequest;
use App\Contracts\Storage\BackupServiceInterface;
use App\Contracts\System\StorageBootstrapperInterface;
use App\Core\Service\Maintenance\CronScheduler;

/**
 * TODO
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class SystemMaintenanceMiddleware implements MiddlewareInterface
{
    public function __construct(
        private StorageBootstrapperInterface $bootstrapper,
        private CronScheduler $cronScheduler,
        private BackupServiceInterface $backupService,
    ) {
    }

    public function process(ServerRequest $request, callable $next): mixed
    {
        try {
            $this->bootstrapper->bootstrap();
            $this->cronScheduler->runIfNeeded();
            $this->backupService->checkAutoBackup();
        } catch (\Throwable $e) {
            \error_log('Bootstrapping Warning: ' . $e->getMessage());
        }

        return $next($request);
    }
}
