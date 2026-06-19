<?php

/**
 * Zeigt den Verlauf der Entwicklung aus der CHANGELOG.MD
 *
 * Path: public/changelog.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */

declare(strict_types=1);

use App\Application\ChangelogController;

$container = require_once __DIR__ . '/../src/Bootstrap/app.php';
$container->get(ChangelogController::class)->handleRequest($_GET);
