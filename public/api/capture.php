<?php

/**
 * API-Endpunkt für PayPal Capture
 *
 * Finalisiert den Antrag nach erfolgreicher Zahlung.
 *
 * Path: public/api/capture.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */

declare(strict_types=1);

use App\Application\ApiController;

$container = require_once __DIR__ . '/../../src/Bootstrap/app.php';
$container->get(ApiController::class)->handle('capture');
