<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Application\RequiresPermissionInterface;
use App\Contracts\Application\ViewActionInterface;
use App\Infrastructure\Maintenance\GitHubUpdaterService;
use App\Infrastructure\System\SystemInfoService;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('force_update_check')]
final readonly class SystemForceUpdateCheckAction implements ViewActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private GitHubUpdaterService $updater,
        private SystemInfoService $sysInfo,
        private SessionManager $sessionManager,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'system.update.view';
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $currentVersion = $this->sysInfo->getCurrentVersion();
            $updateData     = $this->updater->checkForUpdate($currentVersion, true);

            if ($updateData !== null) {
                $this->sessionManager->addFlash('success', "Erfolg: Eine neue Version ({$updateData['version']}) ist verfügbar! Wechseln Sie zum Dashboard, um das Update zu starten.");
            } else {
                $this->sessionManager->addFlash('info', 'Hinweis: GitHub geprüft. Sie nutzen bereits die aktuellste Version.');
            }

            return new RedirectResponse('admin.php');
        } catch (\Throwable $e) {
            \error_log('Manual Update Check Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            $this->sessionManager->addFlash('error', 'Fehler bei der Update-Prüfung: ' . $e->getMessage());

            return new RedirectResponse('admin.php');
        }
    }
}
