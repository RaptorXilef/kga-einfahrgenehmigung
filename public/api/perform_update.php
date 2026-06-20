<?php

/**
 * API-Endpunkt zur Durchführung eines System-Updates.
 *
 * Nimmt die Download-URL des Updates entgegen und stößt den Entpack-
 * und Kopiervorgang über den GitHubUpdaterService an.
 *
 * Path: public/api/perform_update.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */

declare(strict_types=1);

use App\Application\ApiController;
use App\Application\Http\ServerRequest;

$container = require_once __DIR__ . '/../../src/Bootstrap/app.php';

$req = new ServerRequest($_GET, $_POST, $_FILES, $_SERVER);
$container->get(ApiController::class)->handle($req, 'perform_update', 'system.update.execute');
