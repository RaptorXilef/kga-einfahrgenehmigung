<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Http\ServerRequest;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Storage\BackupServiceInterface;

/**
 * TODO DOCBLOCK
 * Action zum erstellen eines Backups
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class SystemCreateBackupAction implements ActionInterface
{
    public function __construct(
        private BackupServiceInterface $backupService,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            // 'manual_trigger' ist nicht in der Config-Liste,
            // daher sichert der BackupService automatisch ALLE Bereiche!
            $folder = $this->backupService->createBackup('manual_trigger');

            return "Erfolg: Vollständiges Backup erstellt in Ordner '" . \basename($folder) . "'.";
        } catch (\Throwable $e) {
            return 'Fehler beim Backup: ' . $e->getMessage();
        }
    }
}
