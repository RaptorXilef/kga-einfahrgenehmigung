<?php

/**
 * Datenschutz Seite Einstiegspunkt
 *
 * Path: public/datenschutz.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */

declare(strict_types=1);

use App\Application\LegalController;

$container  = require_once __DIR__ . '/../src/Bootstrap/app.php';
$controller = $container->get(LegalController::class);
$controller->renderDatenschutz();
