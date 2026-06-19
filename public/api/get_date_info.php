<?php

/**
 * API: Ruhe- und Feiertage abrufen
 *
 * Path: public/api/get_date_info.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */

declare(strict_types=1);

use App\Application\ApiController;

$container = require_once __DIR__ . '/../../src/Bootstrap/app.php';
$container->get(ApiController::class)->handle('get_date_info');
