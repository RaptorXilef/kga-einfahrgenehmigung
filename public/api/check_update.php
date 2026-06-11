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

try {
    $container = require_once __DIR__ . '/../../src/Bootstrap/app.php';
    JsonResponse::enforceCsrfProtection();

    // Prüfen ob Admin eingeloggt ist (Sicherheit!)
    $auth = $container->get(\App\Core\Service\AuthService::class);
    if (! $auth->isLoggedIn()) {
        JsonResponse::error('Nicht autorisiert.', 403);
    }

    $updater = $container->get(GitHubUpdaterService::class);
    $config  = $container->get(ConfigInterface::class);

    // Version aus package.json auslesen (Single Source of Truth)
    $currentVersion  = 'v0.0.0';
    $packageJsonPath = \rtrim((string) $config->get('root_path'), '/\\') . '/package.json';

    if (\file_exists($packageJsonPath)) {
        $pkgData = \json_decode((string) \file_get_contents($packageJsonPath), true);
        if (\is_array($pkgData) && isset($pkgData['version'])) {
            $currentVersion = 'v' . $pkgData['version'];
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
