<?php

/**
 * API-Endpunkt für PayPal Capture
 *
 * Finalisiert den Antrag nach erfolgreicher Zahlung.
 *
 * Path: public/api/capture.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */

declare(strict_types=1);

use App\Application\ApiController;

$container = require_once __DIR__ . '/../../src/Bootstrap/app.php';
$container->get(ApiController::class)->handle('capture');
