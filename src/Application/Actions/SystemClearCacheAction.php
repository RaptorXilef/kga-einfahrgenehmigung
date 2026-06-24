<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Application\RequiresPermissionInterface;
use App\Infrastructure\Maintenance\MigrationService;

/**
 * Action zum Leeren des Anwendungs-Caches.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class SystemClearCacheAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private MigrationService $migrationService,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'dashboard.migration.delete-cache.execute';
    }

    public function execute(ServerRequest $request): mixed
    {
        $msg = $this->migrationService->clearCache();

        return new RedirectResponse('admin.php?msg=' . \urlencode($msg));
    }
}
