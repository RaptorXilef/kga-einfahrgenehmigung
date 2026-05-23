<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

// Path: public/api/create_order_for_token.php

declare(strict_types=1);

use App\Contracts\Payment\PaymentProviderInterface;
use App\Core\Service\PermitService;

/**
 * PayPal Order Erstellung - Nutzt den Preis-Snapshot
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
