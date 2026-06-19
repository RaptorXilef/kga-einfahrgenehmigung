<?php

/**
 * PayPal Order Erstellung - Nutzt den Preis-Snapshot
 *
 * Path: public/api/create_order_for_token.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */

declare(strict_types=1);

use App\Application\ApiController;

$container = require_once __DIR__ . '/../../src/Bootstrap/app.php';
$container->get(ApiController::class)->handle('create_order');
