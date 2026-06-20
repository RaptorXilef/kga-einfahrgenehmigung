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
use App\Application\Http\ServerRequest;

$container = require_once __DIR__ . '/../src/Bootstrap/app.php';

$req = new ServerRequest($_GET, $_POST, $_FILES, $_SERVER);
$container->get(HistoryController::class)->handleRequest($req);
