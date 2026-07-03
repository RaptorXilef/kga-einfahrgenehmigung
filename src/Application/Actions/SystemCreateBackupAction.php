<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Application\RequiresPermissionInterface;
use App\Contracts\Storage\BackupServiceInterface;

/**
 * TODO DOCBLOCK
 * Action zum erstellen eines Backups
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class SystemCreateBackupAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private BackupServiceInterface $backupService,
        private SessionManager $sessionManager,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'dashboard.migration.backup.execute';
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $folder = $this->backupService->createBackup('manual_trigger');
            $this->sessionManager->addFlash('success', "Erfolg: Vollständiges Backup erstellt in Ordner '" . \basename($folder) . "'.");

            return new RedirectResponse('admin.php');
        } catch (\Throwable $e) {
            \error_log('Manual Backup Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            $this->sessionManager->addFlash('error', 'Fehler beim Backup: ' . $e->getMessage());

            return new RedirectResponse('admin.php');
        }
    }
}
