<?php

/**
 * PayPal Order Erstellung - Nutzt den Preis-Snapshot
 *
 * Path: public/api/create_order_for_token.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */

declare(strict_types=1);

use App\Contracts\Payment\PaymentProviderInterface;
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
        throw new \Exception('Kein Token angegeben');
    }

    // Warteraum laden
    // Der Service weiß bereits durch seine Config, ob er JSON oder SQL nutzen muss
    $permitService = $container->get(PermitService::class);
    $tempRequest   = $permitService->getVerifiedRequest($token);

    if ($tempRequest === null) { // Expliziter Check statt if (!$tempRequest)
        throw new \Exception('Sitzung nicht gefunden oder abgelaufen');
    }

    // Preis aus Snapshot nutzen
    $payment = $container->get(PaymentProviderInterface::class);
    $orderId = $payment->createOrder((float) $tempRequest['preisSnapshot']);

    echo \json_encode($orderId ? ['id' => $orderId] : ['success' => false, 'error' => 'PayPal Error']);
} catch (\Throwable $e) {
    echo \json_encode(['success' => false, 'error' => $e->getMessage()]);
}
