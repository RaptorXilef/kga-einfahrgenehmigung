<?php

/**
 * API-Endpunkt für PayPal Capture
 *
 * Finalisiert den Antrag nach erfolgreicher Zahlung.
 *
 * Path: public/api/capture.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */

declare(strict_types=1);

use App\Application\PaymentController;

// Zentraler Bootstrapper laden
$container = require_once __DIR__ . '/../../src/Bootstrap/app.php';

// --- CSRF SECURITY GATEKEEPER ---
$providedToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$sessionToken  = $_SESSION['csrf_token'] ?? '';

if ($sessionToken === '' || ! \hash_equals($sessionToken, $providedToken)) {
    \http_response_code(401);
    echo \json_encode(['success' => false, 'error' => 'Unauthorized: Invalid Security Token']);
    exit;
}
// --------------------------------

$controller = $container->get(PaymentController::class);
$controller->handleCapture();
