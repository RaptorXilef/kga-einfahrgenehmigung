<?php

/**
 * Bestätigungsseite für erfolgreiche Antragstellung.
 *
 * Path: public/success.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */

declare(strict_types=1);

use App\Application\Actions\SuccessAction;
use App\Application\FrontendController;

$container = require_once __DIR__ . '/../src/Bootstrap/app.php';
$action    = $container->get(SuccessAction::class);
$container->get(FrontendController::class)->handleRequest($action, $_GET);
