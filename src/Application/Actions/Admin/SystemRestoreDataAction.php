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
 * Action zur System-Wiederherstellung (Restore) aus einem Backup.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[Route('POST', '/restore_data')]
final readonly class SystemRestoreDataAction implements ActionInterface, RequiresPermissionInterface
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

    /**
     * Führt eine System-Wiederherstellung (Restore) aus einem Backup durch.
     * Stellt Daten für das angegebene Ziel aus dem gewählten Zeitstempel wieder her.
     *
     * @return string Statusmeldung über den Erfolg oder Misserfolg des Restores.
     */
    public function execute(ServerRequest $request): mixed
    {
        $filename = \trim($request->post['filename'] ?? '');
        $target = \trim($request->post['target'] ?? '');
        $mode = (int) ($request->post['mode'] ?? 1);

        if ($filename === '' || $target === '' || !\in_array($mode, [1, 2, 3], true)) {
            $this->sessionManager->addFlash('error', 'Ungültige Wiederherstellungs-Parameter.');

            return new RedirectResponse('admin.php?focus=tab-backup');
        }

        try {
            // Vor dem riskanten Restore IMMER ein Sicherheits-Backup des aktuellen Ist-Zustands machen
            $safetyBackup = $this->backupService->createBackup('all');

            $this->backupService->restoreBackup($filename, $mode, $target);

            $this->auditLogger->log('SYSTEM_RESTORE', "Backup '{$filename}' (Ziel: {$target}) im Modus {$mode} erfolgreich wiederhergestellt.");
            $this->sessionManager->addFlash('success', "Wiederherstellung erfolgreich! Ein Sicherheits-Backup des vorherigen Zustands ({$safetyBackup}) wurde vorsichtshalber erstellt.");
        } catch (Throwable $e) {
            $this->auditLogger->log('SYSTEM_RESTORE_ERROR', "Fehler bei Wiederherstellung von '{$filename}': " . $e->getMessage());
            $this->sessionManager->addFlash('error', 'Fehler bei der Wiederherstellung: ' . $e->getMessage());
        }

        return new RedirectResponse('admin.php?focus=tab-backup');
    }
}
