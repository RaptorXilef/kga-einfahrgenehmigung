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

use App\Application\Response\JsonResponse;
use App\Core\Service\PermitService;

try {
    $container = require_once __DIR__ . '/../../src/Bootstrap/app.php';
    JsonResponse::enforceCsrfProtection();

    // SICHERHEIT: Nur POST-Anfragen erlauben
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        JsonResponse::error('Methode nicht erlaubt.', 405);
    }

    // Von $_GET auf $_POST geändert
    $token = (string) ($_POST['token'] ?? '');
    if ($token === '') {
        JsonResponse::error('Kein Token angegeben');
    }

    $service = $container->get(PermitService::class);

    // Verschiebt Daten von verified_pending(.json) nach permits_active(.json)
    // und triggert den Mail-Versand an Nutzer & Vorstand
    $permit = $service->finaliseRequest($token, 'offen', 'Zahlung per Überweisung gewählt');

    JsonResponse::success(['code' => $permit->code]);

} catch (\Throwable $e) {
    JsonResponse::error($e->getMessage());
}
