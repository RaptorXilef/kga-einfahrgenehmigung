<?php

/**
 * Haupteinstiegspunkt der Anwendung.
 *
 * Initialisiert die Umgebung und delegiert Anfragen an den PermitService.
 * Trennt Request-Handling von der Geschäftslogik.
 *
 * Path: public/index.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */

declare(strict_types=1);

use App\Application\PermitController;

$container = require_once __DIR__ . '/../src/Bootstrap/app.php';
$container->get(PermitController::class)->handleRequest($_POST, $_GET);
