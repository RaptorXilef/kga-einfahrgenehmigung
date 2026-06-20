<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Http\ServerRequest;
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
        private GitHubUpdaterService $updater,
        private SystemInfoService $sysInfo,
    ) {
    }

    public function execute(ServerRequest $request): mixed
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
