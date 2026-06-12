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

    // Sicherheitsprüfung
    $auth = $container->get(\App\Core\Service\AuthService::class);

    // Reiner Login reicht nicht, es muss das strikte Update-Execute-Recht vorliegen!
    if (! $auth->isLoggedIn() || ! $auth->hasPermission('system.update.execute')) {
        JsonResponse::error('Nicht autorisiert. Es fehlen die Rechte für System-Updates.', 403);
    }

    // JSON Body auslesen (Da wir mit fetch arbeiten)
    try {
        $raw   = \file_get_contents('php://input');
        $input = $raw === '' ? [] : \json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);
    } catch (\JsonException $e) {
        JsonResponse::error('Bad Request: Ungültiges JSON-Format gesendet.', 400);
    }
    $zipUrl = $input['zip_url'] ?? '';

    if ($zipUrl === '') {
        JsonResponse::error('Keine Download-URL übergeben.');
    }

    // TODO URL
    // Strikter SSRF-Schutz! URL muss zwingend zum offiziellen Repository gehören.
    $allowedPrefix = 'https://github.com/RaptorXilef/kga-einfahrgenehmigung/releases/download/';
    if (! \str_starts_with($zipUrl, $allowedPrefix)) {
        JsonResponse::error('Sicherheitsverletzung: Ungültige Update-Quelle (SSRF Block).');
    }

    $updater = $container->get(GitHubUpdaterService::class);
    $updater->performUpdate($zipUrl);

    JsonResponse::success(['message' => 'Update erfolgreich installiert!']);

} catch (\Throwable $e) {
    JsonResponse::error($e->getMessage());
}
