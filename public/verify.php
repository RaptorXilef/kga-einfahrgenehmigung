<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * Verifizierungs-Einstiegspunkt
 *
 * @file public/verify.php
 */

declare(strict_types=1);

use App\Application\VerificationController;

// Lädt die Bootstrap-Logik und liefert direkt den Container
$container = require_once __DIR__ . '/../src/Bootstrap/app.php';

$controller = $container->get(VerificationController::class);

$controller->handleRequest($_GET, $_POST);
