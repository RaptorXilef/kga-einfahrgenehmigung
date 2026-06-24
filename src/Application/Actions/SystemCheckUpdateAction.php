<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\ApiCheckUpdateRequest;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use App\Contracts\Application\RequiresPermissionInterface;
use App\Contracts\Application\ViewActionInterface;
use App\Infrastructure\Maintenance\GitHubUpdaterService;
use App\Infrastructure\System\SystemInfoService;

/**
 * Action für die asynchrone Prüfung auf GitHub-Updates.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class SystemCheckUpdateAction implements ViewActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private GitHubUpdaterService $updater,
        private SystemInfoService $sysInfo,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'system.update.view';
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto            = ApiCheckUpdateRequest::fromArray($request->input);
            $currentVersion = $this->sysInfo->getCurrentVersion();

            // Reicht das "force" Flag durch, um den 24h-Cache zu umgehen
            $updateData = $this->updater->checkForUpdate($currentVersion, $dto->force);

            return JsonResponse::success(['update_available' => $updateData !== null, 'data' => $updateData]);
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage());
        }
    }
}
