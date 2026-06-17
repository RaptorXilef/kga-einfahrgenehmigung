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

use App\Application\Actions\DatenschutzAction;

$container = require_once __DIR__ . '/../src/Bootstrap/app.php';
$action    = $container->get(DatenschutzAction::class);
$action->execute($_GET);
