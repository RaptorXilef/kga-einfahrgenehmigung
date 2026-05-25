<?php

/**
 * API: Abschluss für Überweisungen
 * Überführt den Antrag in die Hauptdatenbank.
 *
 * Path: public/api/finalize_wire.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */

declare(strict_types=1);

use App\Core\Service\PermitService;

\header('Content-Type: application/json');

try {
    // Bootstrapper laden
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

    $token = (string) ($_GET['token'] ?? '');
    if ($token === '') {
        echo \json_encode(['success' => false, 'error' => 'Kein Token angegeben']);
        exit;
    }

    $service = $container->get(PermitService::class);

    // Verschiebt Daten von verified_pending(.json) nach permits_active(.json)
    // und triggert den Mail-Versand an Nutzer & Vorstand
    $permit = $service->finaliseRequest($token, 'offen', 'Zahlung per Überweisung gewählt');

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
