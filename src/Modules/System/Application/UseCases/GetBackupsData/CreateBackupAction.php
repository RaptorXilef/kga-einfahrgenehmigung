<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\GetBackupsData;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Storage\BackupServiceInterface;
use App\Modules\System\Application\Services\AuditLoggerService;
use Override;
use Throwable;

/**
 * Erstellt manuell ein neues ZIP-Datenbank-Backup über das Admin-Dashboard.
 */
#[Route('POST', '/create_backup')]
#[RequiresAuth]
final readonly class CreateBackupAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private BackupServiceInterface $backupService,
        private AuditLoggerService $auditLogger,
        private SessionManager $sessionManager,
    ) {
    }

    #[Override]
    public function getRequiredPermission(): string
    {
        return 'system.backup.manage';
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        $target = \trim((string) ($request->post['target'] ?? 'all'));
        if ($target === '') {
            $target = 'all';
        }

        try {
            $filename = $this->backupService->createBackup($target);

            $this->auditLogger->log('SYSTEM_BACKUP_CREATE', "Manuelles Backup '{$filename}' (Ziel: {$target}) erfolgreich erstellt.");
            $this->sessionManager->addFlash('success', "Backup '{$filename}' wurde erfolgreich erstellt.");
        } catch (Throwable $e) {
            $this->sessionManager->addFlash('error', 'Backup fehlgeschlagen: ' . $e->getMessage());
        }

        return new RedirectResponse('admin?focus=tab-backup');
    }
}
