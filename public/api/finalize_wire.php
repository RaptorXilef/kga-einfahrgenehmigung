<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

// Path: public/api/finalize_wire.php

declare(strict_types=1);

use App\Core\Service\PermitService;

/**
 * API: Abschluss für Überweisungen
 * Überführt den Antrag in die Hauptdatenbank.
 */
\header('Content-Type: application/json');

try {
    // Bootstrapper laden
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

    $token = (string) ($_GET['token'] ?? '');
    if ($token === '') {
        echo \json_encode(['success' => false, 'error' => 'Kein Token angegeben']);
        exit;
    }

    $service = $container->get(PermitService::class);

    // Verschiebt Daten von verified_pending(.json) nach permits_active(.json)
    // und triggert den Mail-Versand an Nutzer & Vorstand
    $permit = $service->finaliseRequest($token, 'wartend', 'Zahlung per Überweisung gewählt');

    echo \json_encode([
        'success' => true,
        'code'    => $permit->code,
    ]);
} catch (\Throwable $e) {
    \http_response_code(400);
    echo \json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ]);
}
