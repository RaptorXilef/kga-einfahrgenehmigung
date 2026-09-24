<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\ManageBackups;

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

#[Route('POST', '/restore_data')]
#[RequiresAuth]
final readonly class RestoreBackupAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private BackupServiceInterface $backupService,
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
        $filename = \trim((string) ($request->post['filename'] ?? ''));
        $mode = (int) ($request->post['mode'] ?? 1);
        $target = \trim((string) ($request->post['target'] ?? 'all'));

        if ($filename === '') {
            $this->sessionManager->addFlash('error', 'Fehler: Keine Backup-Datei ausgewählt.');

            return new RedirectResponse('admin?focus=tab-backup');
        }

        try {
            // Vor jeder Wiederherstellung ein automatisches Sicherheits-Backup anlegen
            $this->backupService->createBackup('all');
            $this->backupService->restoreBackup($filename, $mode, $target);

            $this->auditLogger->log('SYSTEM_BACKUP_RESTORE', "Backup '{$filename}' (Modus: {$mode}, Ziel: {$target}) wiederhergestellt.");
            $this->sessionManager->addFlash('success', "Backup '{$filename}' wurde erfolgreich wiederhergestellt.");
        } catch (Throwable $e) {
            $this->sessionManager->addFlash('error', 'Fehler bei der Wiederherstellung: ' . $e->getMessage());
        }

        return new RedirectResponse('admin?focus=tab-backup');
    }
}
