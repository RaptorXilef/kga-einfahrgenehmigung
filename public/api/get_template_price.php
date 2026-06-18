<?php

/**
 * API: Liefert den Preis für ein Template unter Berücksichtigung von Gutscheinen.
 *
 * Path: public/api/get_template_price.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */

declare(strict_types=1);

use App\Application\ApiController;

$container = require_once __DIR__ . '/../../src/Bootstrap/app.php';
$container->get(ApiController::class)->handle('get_template_price', null, true);
