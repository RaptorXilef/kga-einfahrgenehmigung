<?php

/**
 * Endpoint für Server-Cronjobs (z.B. cron-job.org).
 *
 * Triggert automatisierte Wartungsaufgaben (Archivierung & Backups).
 * Aufruf via: https://deine-domain.de/cron.php?token=DEIN_SECRET
 *
 * Path: public/cron.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */

declare(strict_types=1);

use App\Contracts\Config\ConfigInterface;
use App\Core\Service\Maintenance\CronScheduler;

try {
    $container = require_once __DIR__ . '/../src/Bootstrap/app.php';
    $config    = $container->get(ConfigInterface::class);

    $providedToken = $_GET['token'] ?? '';
    $requiredToken = (string) $config->get('cron_secret', 'unconfigured');

    // Schutz: Nur wenn das Token stimmt oder es via Terminal (CLI) ausgeführt wird
    if (\php_sapi_name() !== 'cli' && $providedToken !== $requiredToken) {
        \http_response_code(403);
        exit('Forbidden: Ungültiges Token.');
    }

    $cron = $container->get(CronScheduler::class);
    $cron->runForce();

    echo "Status 200 OK: Cronjobs (Archivierung & Backup) erfolgreich ausgefuehrt.\n";

} catch (\Throwable $e) {
    \http_response_code(500);
    echo 'Fehler bei der Ausfuehrung: ' . $e->getMessage() . "\n";
}
