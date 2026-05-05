<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * API-Endpunkt für PayPal Capture
 * Finalisiert den Antrag nach erfolgreicher Zahlung.
 *
 * @file public/api/capture.php
 */

declare(strict_types=1);

use App\Application\PaymentController;

// Zentraler Bootstrapper (zwei Ebenen hoch)
$container  = require_once __DIR__ . '/../../src/Bootstrap/app.php';
$controller = $container->get(PaymentController::class);
$controller->handleCapture();
