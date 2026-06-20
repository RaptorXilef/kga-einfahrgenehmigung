<?php

/**
 * Verifizierungs-Einstiegspunkt
 *
 * Path: public/verify.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */

declare(strict_types=1);

use App\Application\Http\ServerRequest;
use App\Application\VerificationController;

$container = require_once __DIR__ . '/../src/Bootstrap/app.php';

$req = new ServerRequest($_GET, $_POST, $_FILES, $_SERVER);
$container->get(VerificationController::class)->handleRequest($req);
