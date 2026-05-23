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

// Zentraler Bootstrapper laden
$container = require_once __DIR__ . '/../../src/Bootstrap/app.php';

// --- CSRF SECURITY GATEKEEPER ---
$providedToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$sessionToken  = $_SESSION['csrf_token'] ?? '';

// Wir erlauben das Secret entweder als X-API-Key Header ODER als Bearer Token
$providedSecret = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (empty($providedSecret) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
    if (\preg_match('/Bearer\s(\S+)/', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
        $providedSecret = $matches[1];
    }
}

if ($sessionToken === '' || ! \hash_equals($sessionToken, $providedToken)) {
    \http_response_code(401);
    echo \json_encode(['success' => false, 'error' => 'Unauthorized: Invalid Security Token']);
    exit;
}
// --------------------------------

$controller = $container->get(PaymentController::class);
$controller->handleCapture();
