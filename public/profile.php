<?php

/**
 * Entry Point: My Profile
 *
 * Path: public/profile.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */

declare(strict_types=1);

use App\Application\UserController;

$container = require_once __DIR__ . '/../src/Bootstrap/app.php';
$container->get(UserController::class)->handleProfileRequest($_POST, $_GET);
