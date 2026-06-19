<?php

/**
 * Datenschutz Seite Einstiegspunkt
 *
 * Path: public/datenschutz.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */

declare(strict_types=1);

use App\Application\Actions\DatenschutzAction;
use App\Application\FrontendController;

$container = require_once __DIR__ . '/../src/Bootstrap/app.php';
$action    = $container->get(DatenschutzAction::class);
$container->get(FrontendController::class)->handleRequest($action, $_GET);
