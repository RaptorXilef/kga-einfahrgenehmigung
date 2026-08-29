<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\DTO\SystemMaintenanceRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Maintenance\MigrationServiceInterface;
use App\Core\Service\AuditLoggerService;

/**
 * Action zur System-Wiederherstellung (Restore) aus einem Backup.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[Route('GET', '/restore_data')]
#[Route('POST', '/restore_data')]
final readonly class SystemRestoreDataAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private MigrationServiceInterface $migrationService,
        private SessionManager $sessionManager,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'system.maintenance.execute';
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
        $this->auditLogger->log('SYSTEM_BACKUP_RESTORE', "Daten aus Backup wiederhergestellt. Ziel: {$dto->target}, Timestamp: {$dto->timestamp}");

        $this->sessionManager->addFlash('success', $msg);

        return new RedirectResponse('admin.php');
    }
}
