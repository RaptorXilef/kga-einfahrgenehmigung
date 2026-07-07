<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use App\Contracts\Maintenance\UpdateMigrationServiceInterface;
use App\Core\Service\AuditLoggerService;
use App\Core\Service\AuthService;

/**
 * Action zum Ausführen von DB-Migrationen nach einem Update (Phase 2).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('finalize_update')]
final readonly class SystemFinalizeUpdateAction implements ViewActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private AuthService $auth,
        private UpdateMigrationServiceInterface $migrationService,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'system.update.execute';
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $executedScripts = $this->migrationService->runAllPending();
            $this->auth->refreshSessionPermissions($this->auth->getGroup());

            $msg = empty($executedScripts)
                ? 'Update abgeschlossen. System ist auf dem neuesten Stand.'
                : 'Update abgeschlossen. Datenbank aktualisiert: ' . \implode(', ', $executedScripts);

            $this->auditLogger->log('SYSTEM_UPDATE_FINALIZE', 'Update-Prozess finalisiert. ' . $msg);

            return JsonResponse::success(['message' => $msg, 'executed' => $executedScripts]);
        } catch (\Throwable $e) {
            return JsonResponse::error('Fehler bei der Datenbank-Migration: ' . $e->getMessage());
        }
    }
}
