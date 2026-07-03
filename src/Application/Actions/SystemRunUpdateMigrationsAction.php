<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Application\RequiresPermissionInterface;
use App\Infrastructure\Maintenance\UpdateMigrationService;

/**
 * TODO DOCBLOCK
 * Action zum manuellen Auslosen der Migrationsscripte der Updates
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class SystemRunUpdateMigrationsAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private UpdateMigrationService $migrationService,
        private SessionManager $sessionManager,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'system.update.execute';
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $executed = $this->migrationService->runAllPending();

            if (empty($executed)) {
                $this->sessionManager->addFlash('info', 'Hinweis: Es gab keine neuen Datenbank-Skripte auszuführen.');
            } else {
                $this->sessionManager->addFlash('success', 'Erfolg: Folgende Datenbank-Skripte wurden ausgeführt: ' . \implode(', ', $executed));
            }

            return new RedirectResponse('admin.php');
        } catch (\Throwable $e) {
            \error_log('Manual Update Migration Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            $this->sessionManager->addFlash('error', 'Fehler bei der Ausführung: ' . $e->getMessage());

            return new RedirectResponse('admin.php');
        }
    }
}
