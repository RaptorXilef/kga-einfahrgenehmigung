<?php

/**
 * API-Endpunkt zur Durchführung eines System-Updates.
 *
 * Nimmt die Download-URL des Updates entgegen und stößt den Entpack-
 * und Kopiervorgang über den GitHubUpdaterService an.
 *
 * Path: public/api/perform_update.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */

declare(strict_types=1);

use App\Application\Response\JsonResponse;
use App\Core\Service\GitHubUpdaterService;

try {
    $container = require_once __DIR__ . '/../../src/Bootstrap/app.php';
    JsonResponse::enforceCsrfProtection(); // Schutz vor Cross-Site Request Forgery

    $auth = $container->get(\App\Core\Service\AuthService::class);
    if (! $auth->isLoggedIn()) {
        JsonResponse::error('Nicht autorisiert.', 403);
    }

    // JSON Body auslesen (Da wir mit fetch arbeiten)
    $input  = \json_decode(\file_get_contents('php://input'), true) ?? [];
    $zipUrl = $input['zip_url'] ?? '';

    if ($zipUrl === '') {
        JsonResponse::error('Keine Download-URL übergeben.');
    }

    $updater = $container->get(GitHubUpdaterService::class);
    $updater->performUpdate($zipUrl);

    JsonResponse::success(['message' => 'Update erfolgreich installiert!']);

} catch (\Throwable $e) {
    JsonResponse::error($e->getMessage());
}
