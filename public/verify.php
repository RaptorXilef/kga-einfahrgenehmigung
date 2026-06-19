<?php

/**
 * Verifizierungs-Einstiegspunkt
 *
 * Path: public/verify.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */

declare(strict_types=1);

use App\Application\VerificationController;

$container = require_once __DIR__ . '/../src/Bootstrap/app.php';
$container->get(VerificationController::class)->handleRequest($_GET, $_POST);
