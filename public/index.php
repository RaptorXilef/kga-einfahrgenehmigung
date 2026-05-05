<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * Haupteinstiegspunkt der Anwendung.
 *
 * Initialisiert die Umgebung und delegiert Anfragen an den PermitService.
 * Trennt Request-Handling von der Geschäftslogik.
 *
 * @file      public/index.php
 */

declare(strict_types=1);

use App\Application\PermitController;

// Lädt die Bootstrap-Logik und liefert direkt den Container
$container = require_once __DIR__ . '/../src/Bootstrap/app.php';

$controller = $container->get(PermitController::class);

$controller->handleRequest($_POST, $_GET);
