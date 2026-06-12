<?php

/**
 * API-Endpunkt zur Prüfung auf neue Updates via GitHub.
 *
 * Liest die aktuelle Version aus der package.json und gleicht sie über den
 * GitHubUpdaterService mit dem neuesten Release ab.
 *
 * Path: public/api/check_update.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */

declare(strict_types=1);

use App\Application\Response\JsonResponse;
use App\Contracts\Config\ConfigInterface;
use App\Core\Service\GitHubUpdaterService;
use App\Infrastructure\Storage\JsonHelper;

try {
    $container = require_once __DIR__ . '/../../src/Bootstrap/app.php';
    JsonResponse::enforceCsrfProtection();

    // Sicherheitsprüfung
    $auth = $container->get(\App\Core\Service\AuthService::class);

    // Reiner Login reicht nicht, es muss das strikte Update-Execute-Recht vorliegen!
    if (! $auth->isLoggedIn() || ! $auth->hasPermission('system.update.view')) {
        JsonResponse::error('Nicht autorisiert. Es fehlen die Rechte für System-Updates.', 403);
    }

    $updater = $container->get(GitHubUpdaterService::class);
    $config  = $container->get(ConfigInterface::class);

    // Version aus package.json auslesen (Single Source of Truth)
    $currentVersion  = 'v0.0.0';
    $packageJsonPath = \rtrim((string) $config->get('root_path'), '/\\') . '/package.json';

    if (\file_exists($packageJsonPath)) {
        try {
            $pkgData = JsonHelper::read($packageJsonPath);
            if (\is_array($pkgData) && isset($pkgData['version'])) {
                $currentVersion = 'v' . $pkgData['version'];
            }
        } catch (\RuntimeException $e) {
            // Ignorieren: Version bleibt v0.0.0, was ein Auto-Update erzwingt!
        }
    }

    $updateData = $updater->checkForUpdate($currentVersion);

    JsonResponse::success([
        'update_available' => $updateData !== null,
        'data'             => $updateData,
    ]);

} catch (\Throwable $e) {
    JsonResponse::error($e->getMessage());
}
