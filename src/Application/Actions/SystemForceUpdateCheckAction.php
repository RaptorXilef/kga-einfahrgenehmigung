<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Http\ServerRequest;
use App\Contracts\Application\ActionInterface;
use App\Core\Service\SystemInfoService;
use App\Infrastructure\Maintenance\GitHubUpdaterService;

/**
 * Action zum manuellen Auslösen der Update-Suche
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class SystemForceUpdateCheckAction implements ActionInterface
{
    public function __construct(
        private GitHubUpdaterService $updater,
        private SystemInfoService $sysInfo,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $currentVersion = $this->sysInfo->getCurrentVersion();

            // force = true umgeht den 24h-Cache und fragt live bei GitHub an!
            $updateData = $this->updater->checkForUpdate($currentVersion, true);

            if ($updateData !== null) {
                return "Erfolg: Eine neue Version ({$updateData['version']}) ist verfügbar! Wechseln Sie zum Dashboard, um das Update zu starten.";
            }

            return 'Hinweis: GitHub geprüft. Sie nutzen bereits die aktuellste Version.';
        } catch (\Throwable $e) {
            \error_log('Manual Update Check Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());

            return 'Fehler bei der Update-Prüfung: ' . $e->getMessage();
        }
    }
}
