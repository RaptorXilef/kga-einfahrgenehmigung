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
use App\Contracts\System\AuditLoggerInterface;
use Override;
use Throwable;

/**
 * Action zum Wiederherstellen eines ZIP-Backups (inkl. automatischem Sicherheits-Snapshot vorab).
 */
#[Route('POST', '/restore_data')]
#[RequiresAuth]
final readonly class RestoreBackupAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private BackupServiceInterface $backupService,
        private AuditLoggerInterface $auditLogger,
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
        $filename = \basename(\trim((string) ($request->post['filename'] ?? '')));
        $mode = (int) ($request->post['mode'] ?? 1);
        $target = \trim((string) ($request->post['target'] ?? 'all'));

        if ($filename === '' || !\in_array($mode, [1, 2, 3], true)) {
            $this->sessionManager->addFlash('error', 'Ungültige Parameter für die Wiederherstellung.');

            return new RedirectResponse('admin?focus=tab-backup');
        }

        try {
            // Sicherheits-Snapshot des aktuellen Zustands vor dem Überschreiben anlegen
            $this->backupService->createBackup('all');
            $this->backupService->restoreBackup($filename, $mode, $target);

            $this->auditLogger->log(
                'SYSTEM_BACKUP_RESTORE',
                "Backup '{$filename}' wiederhergestellt (Modus: {$mode}, Ziel: {$target}).",
            );
            $this->sessionManager->addFlash('success', "Daten aus '{$filename}' wurden erfolgreich wiederhergestellt.");
        } catch (Throwable $e) {
            $this->sessionManager->addFlash('error', 'Fehler bei der Wiederherstellung: ' . $e->getMessage());
        }

        return new RedirectResponse('admin?focus=tab-backup');
    }
}
