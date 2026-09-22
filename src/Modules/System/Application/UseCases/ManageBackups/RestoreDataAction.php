<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\ManageBackups;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Storage\BackupServiceInterface;
use App\Modules\System\Application\Services\AuditLoggerService;
use Throwable;

#[Route('POST', '/restore_data')]
final readonly class RestoreDataAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private BackupServiceInterface $backupService,
        private SessionManager $sessionManager,
        private AuditLoggerService $auditLogger,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'system.backup.manage';
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = RestoreDataRequest::fromArray($request->post);

            $safetyBackup = $this->backupService->createBackup('all');
            $this->backupService->restoreBackup($dto->filename, $dto->mode, $dto->target);

            $this->auditLogger->log('SYSTEM_RESTORE', "Backup '{$dto->filename}' (Ziel: {$dto->target}) im Modus {$dto->mode} erfolgreich wiederhergestellt.");
            $this->sessionManager->addFlash('success', "Wiederherstellung erfolgreich! Ein Sicherheits-Backup des vorherigen Zustands ({$safetyBackup}) wurde vorsichtshalber erstellt.");
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());
        } catch (Throwable $e) {
            $this->auditLogger->log('SYSTEM_RESTORE_ERROR', 'Fehler bei Wiederherstellung aus POST-Request: ' . $e->getMessage());
            $this->sessionManager->addFlash('error', 'Fehler bei der Wiederherstellung: ' . $e->getMessage());
        }

        return new RedirectResponse('admin?focus=tab-backup');
    }
}
