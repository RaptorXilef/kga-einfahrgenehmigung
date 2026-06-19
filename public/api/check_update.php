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
 */

declare(strict_types=1);

use App\Application\ApiController;

$container = require_once __DIR__ . '/../../src/Bootstrap/app.php';
$container->get(ApiController::class)->handle('check_update', 'system.update.view');
