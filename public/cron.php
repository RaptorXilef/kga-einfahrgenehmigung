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

use App\Application\CronController;

$container = require_once __DIR__ . '/../src/Bootstrap/app.php';
$container->get(CronController::class)->handleRequest($_GET);
