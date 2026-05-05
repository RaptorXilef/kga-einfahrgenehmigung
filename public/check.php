<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * Validierungsschnittstelle für Ausnahmegenehmigungen (v0.9.5).
 *
 * Prüft die Gültigkeit eines Codes und unterscheidet mittels Token-Validierung
 * zwischen der öffentlichen Ansicht und der detaillierten Vorstandsansicht.
 *
 * @file      public/check.php
 */

declare(strict_types=1);

use App\Application\CheckController;

// Lädt die Bootstrap-Logik und liefert direkt den Container
$container  = require_once __DIR__ . '/../src/Bootstrap/app.php';
$controller = $container->get(CheckController::class);
$controller->handleRequest($_GET);
