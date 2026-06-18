<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Response\JsonResponse;
use App\Contracts\Application\ViewActionInterface;
use App\Contracts\Config\ConfigInterface;
use App\Core\Service\GitHubUpdaterService;
use App\Infrastructure\Storage\JsonHelper;

/**
 * TODO DOCBLOCK
 *
 * Path: src/Application/Actions/SystemCheckUpdateAction.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class SystemCheckUpdateAction implements ViewActionInterface
{
    public function __construct(private ConfigInterface $config, private GitHubUpdaterService $updater)
    {
    }

    public function execute(array $requestData): void
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
    }
}
