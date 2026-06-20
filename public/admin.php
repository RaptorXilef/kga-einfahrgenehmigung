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
use App\Application\Http\ServerRequest;

$container = require_once __DIR__ . '/../src/Bootstrap/app.php';

$req = new ServerRequest($_GET, $_POST, $_FILES, $_SERVER);
$container->get(AdminController::class)->handleRequest($req);
