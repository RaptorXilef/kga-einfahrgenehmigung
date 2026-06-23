<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Http\ServerRequest;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Application\RequiresPermissionInterface;
use App\Infrastructure\Maintenance\UpdateMigrationService;

/**
 * TODO DOCBLOCK
 * Action zum manuellen Auslosen der Migrationsscripte der Updates
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class SystemRunUpdateMigrationsAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private UpdateMigrationService $migrationService,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'system.update.execute';
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $executed = $this->migrationService->runAllPending();
            if (empty($executed)) {
                return 'Hinweis: Es gab keine neuen Datenbank-Skripte auszuführen.';
            }

            return 'Erfolg: Folgende Datenbank-Skripte wurden ausgeführt: ' . \implode(', ', $executed);
        } catch (\Throwable $e) {
            \error_log('Manual Update Migration Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());

            return 'Fehler bei der Ausführung: ' . $e->getMessage();
        }
    }
}
