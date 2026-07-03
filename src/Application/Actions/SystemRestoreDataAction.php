<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\SystemMaintenanceRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Application\RequiresPermissionInterface;
use App\Infrastructure\Maintenance\MigrationService;

/**
 * Action zur System-Wiederherstellung (Restore) aus einem Backup.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class SystemRestoreDataAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private MigrationService $migrationService,
        private SessionManager $sessionManager,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'dashboard.migration.restore.execute';
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
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('admin.php');
        }

        $msg = $this->migrationService->restore($dto->timestamp, $dto->target, $dto->engine);
        $this->sessionManager->addFlash('success', $msg);

        return new RedirectResponse('admin.php');
    }
}
