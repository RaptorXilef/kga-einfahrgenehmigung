<?php

/**
 * PayPal Order Erstellung - Nutzt den Preis-Snapshot
 *
 * Path: public/api/create_order_for_token.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */

declare(strict_types=1);

use App\Application\ApiController;

$container = require_once __DIR__ . '/../../src/Bootstrap/app.php';
$container->get(ApiController::class)->handle('create_order');
