<?php

/**
 * Zeigt den Verlauf der Entwicklung aus der CHANGELOG.MD
 *
 * Path: public/changelog.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */

declare(strict_types=1);

use App\Application\ChangelogController;

$container = require_once __DIR__ . '/../src/Bootstrap/app.php';
$container->get(ChangelogController::class)->handleRequest($_GET);
