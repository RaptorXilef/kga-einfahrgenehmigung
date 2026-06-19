<?php

/**
 * API: Abschluss für Software-Update
 *
 * Path: public/api/finalize_update.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */

declare(strict_types=1);

use App\Application\ApiController;

$container = require_once __DIR__ . '/../../src/Bootstrap/app.php';
$container->get(ApiController::class)->handle('finalize_update', 'system.update.execute');
