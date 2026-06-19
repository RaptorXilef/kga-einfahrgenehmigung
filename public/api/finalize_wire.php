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

$container = require_once __DIR__ . '/../../src/Bootstrap/app.php';
$container->get(ApiController::class)->handle('finalize_wire');
