<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Response\JsonResponse;
use App\Contracts\Application\ViewActionInterface;
use App\Contracts\Config\ConfigInterface;
use App\Infrastructure\Maintenance\GitHubUpdaterService;
use App\Infrastructure\Storage\JsonHelper;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class SystemCheckUpdateAction implements ViewActionInterface
{
    public function __construct(private ConfigInterface $config, private GitHubUpdaterService $updater)
    {
    }

    public function execute(array $requestData): mixed
    {
        try {
            $currentVersion  = 'v0.0.0';
            $packageJsonPath = \rtrim((string) $this->config->get('root_path'), '/\\') . '/package.json';

            if (\file_exists($packageJsonPath)) {
                try {
                    $pkgData = JsonHelper::read($packageJsonPath);
                    if (\is_array($pkgData) && isset($pkgData['version'])) {
                        $currentVersion = 'v' . $pkgData['version'];
                    }
                } catch (\RuntimeException) {
                }
            }

            $updateData = $this->updater->checkForUpdate($currentVersion);
            JsonResponse::success(['update_available' => $updateData !== null, 'data' => $updateData]);
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage());
        }

        return null;
    }
}
