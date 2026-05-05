<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * User-Management Einstiegspunkt v0.9.7
 *
 * @file      public/users.php
 */

declare(strict_types=1);

use App\Application\UserController;

// Lädt die Bootstrap-Logik und liefert direkt den Container
$container = require_once __DIR__ . '/../src/Bootstrap/app.php';

$controller = $container->get(UserController::class);

$controller->handleRequest($_POST);
