<?php

/**
 * Pächter-Verlauf Einstiegspunkt
 *
 * Ermöglicht die Einsicht aller Genehmigungen via Magic Link.
 *
 * Path: public/history.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */

declare(strict_types=1);

use App\Application\HistoryController;

$container = require_once __DIR__ . '/../src/Bootstrap/app.php';
$container->get(HistoryController::class)->handleRequest($_GET, $_POST);
