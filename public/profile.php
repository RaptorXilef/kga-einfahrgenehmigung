<?php

/**
 * Entry Point: My Profile
 *
 * Path: public/profile.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */

declare(strict_types=1);

use App\Application\Http\ServerRequest;
use App\Application\UserController;

$container = require_once __DIR__ . '/../src/Bootstrap/app.php';

$req = new ServerRequest($_GET, $_POST, $_FILES, $_SERVER);
$container->get(UserController::class)->handleProfileRequest($req);
