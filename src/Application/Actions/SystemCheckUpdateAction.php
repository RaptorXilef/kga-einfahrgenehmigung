<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Response\JsonResponse;
use App\Contracts\Application\ViewActionInterface;
use App\Core\Service\SystemInfoService;
use App\Infrastructure\Maintenance\GitHubUpdaterService;

/**
 * Action für die asynchrone Prüfung auf GitHub-Updates.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class SystemCheckUpdateAction implements ViewActionInterface
{
    public function __construct(
        private SystemInfoService $sysInfo,
        private GitHubUpdaterService $updater,
    ) {
    }

    public function execute(array $requestData): mixed
    {
        try {
            $currentVersion = $this->sysInfo->getCurrentVersion();
            $updateData     = $this->updater->checkForUpdate($currentVersion);

            return JsonResponse::success(['update_available' => $updateData !== null, 'data' => $updateData]);
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage());
        }
    }
}
