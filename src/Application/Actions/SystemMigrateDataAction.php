<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\SystemMaintenanceRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Contracts\Application\ActionInterface;
use App\Infrastructure\Maintenance\MigrationService;

/**
 * Action für Daten-Migrationen (Sync/Backup) zwischen Storage-Engines.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class SystemMigrateDataAction implements ActionInterface
{
    public function __construct(
        private MigrationService $migrationService,
    ) {
    }

    /**
     * Führt Daten-Migrationen (Sync/Backup) durch (Sync SQL/JSON).
     *
     * @return string Ergebnis der Migration.
     */
    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = SystemMaintenanceRequest::forMigration($request->post);
        } catch (ValidationException $e) {
            return $e->getMessage();
        }

        return $this->migrationService->execute($dto->target, $dto->direction);
    }
}
