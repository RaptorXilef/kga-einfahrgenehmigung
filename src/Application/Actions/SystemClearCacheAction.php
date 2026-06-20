<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Http\ServerRequest;
use App\Contracts\Application\ActionInterface;
use App\Infrastructure\Maintenance\MigrationService;

/**
 * Action zum Leeren des Anwendungs-Caches.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class SystemClearCacheAction implements ActionInterface
{
    public function __construct(
        private MigrationService $migrationService,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        return $this->migrationService->clearCache();
    }
}
