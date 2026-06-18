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

use App\Application\ApiController;

$container = require_once __DIR__ . '/../../src/Bootstrap/app.php';
$container->get(ApiController::class)->handle('perform_update', 'system.update.execute');
