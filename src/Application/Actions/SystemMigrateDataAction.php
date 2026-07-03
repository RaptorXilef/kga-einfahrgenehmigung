<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\SystemMaintenanceRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
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
        private SessionManager $sessionManager,
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
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('admin.php');
        }

        $msg = $this->migrationService->execute($dto->target, $dto->direction);
        $this->sessionManager->addFlash('success', $msg);

        return new RedirectResponse('admin.php');
    }
}
