<?php

/**
 * API: Abschluss für Überweisungen
 * Überführt den Antrag in die Hauptdatenbank.
 *
 * Path: public/api/finalize_wire.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */

declare(strict_types=1);

use App\Application\ApiController;
use App\Application\Http\ServerRequest;

$container = require_once __DIR__ . '/../../src/Bootstrap/app.php';

$req = new ServerRequest($_GET, $_POST, $_FILES, $_SERVER);
$container->get(ApiController::class)->handle($req, 'finalize_wire');
