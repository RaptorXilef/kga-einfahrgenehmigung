<?php

/**
 * Checkout-Einstiegspunkt
 *
 * Path: public/checkout.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
declare(strict_types=1);

use App\Application\Actions\CheckoutAction;
use App\Application\FrontendController;

$container = require_once __DIR__ . '/../src/Bootstrap/app.php';
$action    = $container->get(CheckoutAction::class);
$container->get(FrontendController::class)->handleRequest($action, $_GET);
