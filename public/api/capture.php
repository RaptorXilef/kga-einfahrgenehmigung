<?php

/**
 * API-Endpunkt für PayPal Capture
 *
 * Finalisiert den Antrag nach erfolgreicher Zahlung.
 *
 * Path: public/api/capture.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */

declare(strict_types=1);

use App\Application\ApiController;
use App\Application\Http\ServerRequest;

$container = require_once __DIR__ . '/../../src/Bootstrap/app.php';

$req = new ServerRequest($_GET, $_POST, $_FILES, $_SERVER);
$container->get(ApiController::class)->handle($req, 'capture');
