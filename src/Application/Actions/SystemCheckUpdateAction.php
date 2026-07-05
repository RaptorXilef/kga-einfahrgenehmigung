<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\DTO\ApiCheckUpdateRequest;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use App\Contracts\Application\RequiresPermissionInterface;
use App\Contracts\Application\ViewActionInterface;
use App\Contracts\System\SystemInfoInterface;
use App\Contracts\System\SystemUpdaterInterface;

/**
 * Action für die asynchrone Prüfung auf GitHub-Updates.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('check_update')]
final readonly class SystemCheckUpdateAction implements ViewActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private SystemUpdaterInterface $updater,
        private SystemInfoInterface $sysInfo,
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
