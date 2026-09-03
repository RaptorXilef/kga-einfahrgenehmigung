<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Storage\BackupServiceInterface;
use App\Core\Service\AuditLoggerService;
use Throwable;

/**
 * Action zum erstellen eines Backups
 */
#[Route('GET', '/create_backup')]
#[Route('POST', '/create_backup')]
final readonly class SystemCreateBackupAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private BackupServiceInterface $backupService,
        private SessionManager $sessionManager,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'system.backup.manage';
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            // Das Ziel wird jetzt dynamisch aus dem Dropdown ausgelesen (Fallback auf 'all')
            $target = \trim((string) ($request->post['target'] ?? 'all'));

            $file = $this->backupService->createBackup($target);

            $this->auditLogger->log('SYSTEM_BACKUP_CREATE', "Ein manuelles Backup (Ziel: {$target}) wurde erstellt (Archiv: " . \basename($file) . ').');

            $this->sessionManager->addFlash('success', "Erfolg: Backup ({$target}) erstellt in Archiv '" . \basename($file) . "'.");

            // Leitet direkt wieder auf das Backups-Tab um
            return new RedirectResponse('admin?focus=tab-backup');
        } catch (Throwable $e) {
            $this->sessionManager->addFlash('error', 'Fehler beim Backup: ' . $e->getMessage());

            return new RedirectResponse('admin?focus=tab-backup');
        }
    }
}
