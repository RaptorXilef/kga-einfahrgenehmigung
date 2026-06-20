<?php

/**
 * API-Endpunkt zur Prüfung auf neue Updates via GitHub.
 *
 * Liest die aktuelle Version aus der package.json und gleicht sie über den
 * GitHubUpdaterService mit dem neuesten Release ab.
 *
 * Path: public/api/check_update.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */

declare(strict_types=1);

use App\Application\ApiController;
use App\Application\Http\ServerRequest;

$container = require_once __DIR__ . '/../../src/Bootstrap/app.php';

$req = new ServerRequest($_GET, $_POST, $_FILES, $_SERVER);
$container->get(ApiController::class)->handle($req, 'check_update', 'system.update.view');
