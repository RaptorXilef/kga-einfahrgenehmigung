<?php

/**
 * User-Management Einstiegspunkt
 *
 * Path: public/users.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */

declare(strict_types=1);

use App\Application\UserController;

$container = require_once __DIR__ . '/../src/Bootstrap/app.php';
$container->get(UserController::class)->handleRequest($_POST, $_GET);
