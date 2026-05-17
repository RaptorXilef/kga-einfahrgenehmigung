<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

// Path: public/api/capture.php

declare(strict_types=1);

use App\Application\PaymentController;

/**
 * API-Endpunkt für PayPal Capture
 * Finalisiert den Antrag nach erfolgreicher Zahlung.
 */

// Zentraler Bootstrapper (zwei Ebenen hoch)
$container  = require_once __DIR__ . '/../../src/Bootstrap/app.php';
$controller = $container->get(PaymentController::class);
$controller->handleCapture();
