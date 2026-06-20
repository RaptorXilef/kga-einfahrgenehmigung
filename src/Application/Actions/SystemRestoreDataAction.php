<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\SystemMaintenanceRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Contracts\Application\ActionInterface;
use App\Infrastructure\Maintenance\MigrationService;

/**
 * Action zur System-Wiederherstellung (Restore) aus einem Backup.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class SystemRestoreDataAction implements ActionInterface
{
    public function __construct(
        private MigrationService $migrationService,
    ) {
    }

    /**
     * Führt eine System-Wiederherstellung (Restore) aus einem Backup durch.
     * Stellt Daten für das angegebene Ziel aus dem gewählten Zeitstempel wieder her.
     *
     * @return string Statusmeldung über den Erfolg oder Misserfolg des Restores.
     */
    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = SystemMaintenanceRequest::forRestore($request->post);
        } catch (ValidationException $e) {
            return $e->getMessage();
        }

        return $this->migrationService->restore($dto->timestamp, $dto->target, $dto->engine);
    }
}
