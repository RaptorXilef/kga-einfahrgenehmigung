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

use App\Application\ApiController;

$container = require_once __DIR__ . '/../../src/Bootstrap/app.php';
$container->get(ApiController::class)->handle('check_update', 'system.update.view');
