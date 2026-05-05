<?php

/**
 * Pächter-Verlauf Einstiegspunkt v0.13.0
 * Ermöglicht die Einsicht aller Genehmigungen via Magic Link.
 *
 * @file public/history.php
 */

declare(strict_types=1);

use App\Application\HistoryController;

// Lädt die Bootstrap-Logik und liefert direkt den Container
$container = require_once __DIR__ . '/../src/Bootstrap/app.php';

$controller = $container->get(HistoryController::class);

$controller->handleRequest($_GET, $_POST);
