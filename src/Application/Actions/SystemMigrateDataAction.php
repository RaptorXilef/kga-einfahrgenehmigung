<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\SystemMaintenanceRequest;
use App\Application\Exception\ValidationException;
use App\Contracts\Application\ActionInterface;
use App\Core\Service\AuthService;
use App\Infrastructure\Maintenance\MigrationService;

/**
 * Action für Daten-Migrationen (Sync/Backup) zwischen Storage-Engines.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class SystemMigrateDataAction implements ActionInterface
{
    public function __construct(
        private AuthService $auth,
        private MigrationService $migrationService,
    ) {
    }

    /**
     * Führt Daten-Migrationen (Sync/Backup) durch (Sync SQL/JSON).
     *
     * @param array<string, mixed> $post
     *
     * @return string Ergebnis der Migration.
     */
    public function execute(array $post): mixed
    {
        try {
            $dto = SystemMaintenanceRequest::forMigration($post);
        } catch (ValidationException $e) {
            return $e->getMessage();
        }

        if (! $this->auth->hasPermission("dashboard.migration.{$dto->target}.{$dto->direction}")) {
            return 'Fehler: Sie haben keine Berechtigung für diese Migrations-Aktion.';
        }

        return $this->migrationService->execute($dto->target, $dto->direction);
    }
}
