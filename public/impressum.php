<?php

/**
 * Impressum Seite Einstiegspunkt
 *
 * Path: public/impressum.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */

declare(strict_types=1);

use App\Application\Actions\ImpressumAction;
use App\Application\FrontendController;

$container = require_once __DIR__ . '/../src/Bootstrap/app.php';
$action    = $container->get(ImpressumAction::class);
$container->get(FrontendController::class)->handleRequest($action, $_GET);
