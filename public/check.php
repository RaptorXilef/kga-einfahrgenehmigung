<?php

/**
 * Validierungsschnittstelle für Ausnahmegenehmigungen
 *
 * Prüft die Gültigkeit eines Codes und unterscheidet mittels Token-Validierung
 * zwischen der öffentlichen Ansicht und der detaillierten Vorstandsansicht.
 *
 * Path: public/check.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */

declare(strict_types=1);

use App\Application\Actions\CheckPermitAction;
use App\Application\FrontendController;
use App\Application\Http\ServerRequest;

$container = require_once __DIR__ . '/../src/Bootstrap/app.php';

$req    = new ServerRequest($_GET, $_POST, $_FILES, $_SERVER);
$action = $container->get(CheckPermitAction::class);
$container->get(FrontendController::class)->handleRequest($action, $req);
