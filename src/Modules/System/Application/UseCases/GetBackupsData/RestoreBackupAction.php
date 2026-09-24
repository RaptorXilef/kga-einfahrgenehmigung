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
 * Stellt einen Datenbank-Snapshot aus einem ZIP-Archiv wieder her.
 * Erzeugt vorab immer ein automatisches Sicherheits-Backup des Ist-Zustands.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[Route('POST', '/restore_data')]
#[RequiresAuth]
final readonly class RestoreBackupAction implements ActionInterface, RequiresPermissionInterface
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
        $filename = \basename(\trim((string) ($request->post['filename'] ?? '')));
        $mode = (int) ($request->post['mode'] ?? 1);
        $target = \trim((string) ($request->post['target'] ?? 'all'));

        if ($filename === '' || !\in_array($mode, [1, 2, 3], true)) {
            $this->sessionManager->addFlash('error', 'Ungültige Parameter für die Wiederherstellung.');

            return new RedirectResponse('admin?focus=tab-backup');
        }

        try {
            // Sicherheits-Backup vor dem Einspielen anlegen
            $safetyBackup = $this->backupService->createBackup('all');

            $this->backupService->restoreBackup($filename, $mode, $target);

            $this->auditLogger->log(
                'SYSTEM_BACKUP_RESTORE',
                "Backup '{$filename}' (Modus: {$mode}, Ziel: {$target}) wiederhergestellt. Sicherheits-Snapshot: {$safetyBackup}",
            );
            $this->sessionManager->addFlash('success', "Daten aus '{$filename}' wurden erfolgreich wiederhergestellt.");
        } catch (Throwable $e) {
            $this->sessionManager->addFlash('error', 'Fehler bei der Wiederherstellung: ' . $e->getMessage());
        }

        return new RedirectResponse('admin?focus=tab-backup');
    }
}
