<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * Admin Einstiegspunkt v0.9.5
 *
 * @file      public/admin.php
 */

declare(strict_types=1);

use App\Application\AdminController;

$container = require_once __DIR__ . '/../src/Bootstrap/app.php';

$controller = $container->get(AdminController::class);

// Delegiere alles an die beweisbare Klasse
$controller->handleRequest($_GET, $_POST);
