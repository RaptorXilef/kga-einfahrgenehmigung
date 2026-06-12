<?php

/**
 * API: Abschluss für Software-Update
 *
 * Path: public/api/finalize_update.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */

declare(strict_types=1);

use App\Application\Response\JsonResponse;
use App\Core\Service\UpdateMigrationService;

try {
    // 1. Container laden (Lädt jetzt den frisch entpackten Code in den RAM!)
    $container = require_once __DIR__ . '/../../src/Bootstrap/app.php';

    JsonResponse::enforceCsrfProtection();

    // 2. Sicherheitsprüfung
    $auth = $container->get(\App\Core\Service\AuthService::class);

    // Reiner Login reicht nicht, es muss das strikte Update-Execute-Recht vorliegen!
    if (! $auth->isLoggedIn() || ! $auth->hasPermission('system.update.execute')) {
        JsonResponse::error('Nicht autorisiert. Es fehlen die Rechte für System-Updates.', 403);
    }

    // 3. Migrationen ausführen
    $migrationService = $container->get(UpdateMigrationService::class);
    $executedScripts  = $migrationService->runAllPending();

    // 4. Aufräumen (z.B. gecachte Berechtigungen der aktuellen Session neu laden)
    $auth->refreshSessionPermissions($auth->getGroup());

    $msg = empty($executedScripts)
        ? 'Update abgeschlossen. System ist auf dem neuesten Stand.'
        : 'Update abgeschlossen. Datenbank aktualisiert: ' . \implode(', ', $executedScripts);

    JsonResponse::success([
        'message'  => $msg,
        'executed' => $executedScripts,
    ]);

} catch (\Throwable $e) {
    JsonResponse::error('Fehler bei der Datenbank-Migration: ' . $e->getMessage());
}
