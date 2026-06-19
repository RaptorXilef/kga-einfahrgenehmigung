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

use App\Application\Actions\CheckPermitAction;
use App\Application\FrontendController;

$container = require_once __DIR__ . '/../src/Bootstrap/app.php';
$action    = $container->get(CheckPermitAction::class);
$container->get(FrontendController::class)->handleRequest($action, $_GET);
