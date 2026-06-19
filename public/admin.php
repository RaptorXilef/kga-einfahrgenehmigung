<?php

/**
 * Admin Einstiegspunkt
 *
 * Path: public/admin.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */

declare(strict_types=1);

use App\Application\AdminController;

$container = require_once __DIR__ . '/../src/Bootstrap/app.php';
$container->get(AdminController::class)->handleRequest($_GET, $_POST);
