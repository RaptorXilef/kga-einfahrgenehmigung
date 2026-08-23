<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\Route;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use App\Contracts\Maintenance\UpdateMigrationServiceInterface;
use App\Core\Service\AuditLoggerService;
use App\Core\Service\AuthService;
use Throwable;

#[Route('POST', '/finalize_update')]
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
            // FIX: getRole() statt getGroup()
            $this->auth->refreshSessionPermissions($this->auth->getRole());

            $msg = $executedScripts === []
                ? 'Update abgeschlossen. System ist auf dem neuesten Stand.'
                : 'Update abgeschlossen. Datenbank aktualisiert: ' . \implode(', ', $executedScripts);

            $this->auditLogger->log('SYSTEM_UPDATE_FINALIZE', 'Update-Prozess finalisiert. ' . $msg);

            return JsonResponse::success(['message' => $msg, 'executed' => $executedScripts]);
        } catch (Throwable $e) {
            return JsonResponse::error('Fehler bei der Datenbank-Migration: ' . $e->getMessage());
        }
    }
}
