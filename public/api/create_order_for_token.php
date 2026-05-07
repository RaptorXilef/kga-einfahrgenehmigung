<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

/**
 * PayPal Order Erstellung - Nutzt den Preis-Snapshot
 *
 * Path: public/api/create_order_for_token.php
 */

declare(strict_types=1);

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Payment\PaymentProviderInterface;
use App\Core\Service\PermitService;

\header('Content-Type: application/json');

try {
    // Bootstrapper laden
    $container = require_once __DIR__ . '/../../src/Bootstrap/app.php';
    $config    = $container->get(ConfigInterface::class);
    $appRoot   = $config->get('root_path');

    $token = (string) ($_GET['token'] ?? '');
    if ($token === '') {
        echo \json_encode(['success' => false, 'error' => 'Kein Token angegeben']);
        exit;
    }

    // Warteraum laden
    // Der Service weiß bereits durch seine Config, ob er JSON oder SQL nutzen muss
    $permitService = $container->get(PermitService::class);
    $tempRequest   = $permitService->getVerifiedRequest($token);

    if ($tempRequest === null) { // Expliziter Check statt if (!$tempRequest)
        echo \json_encode(['success' => false, 'error' => 'Sitzung nicht gefunden']);
        exit;
    }

    // Preis aus Snapshot nutzen
    $payment = $container->get(PaymentProviderInterface::class);
    $orderId = $payment->createOrder((float) $tempRequest['preisSnapshot']);

    echo \json_encode($orderId ? ['id' => $orderId] : ['success' => false, 'error' => 'PayPal Error']);
} catch (\Throwable $e) {
    echo \json_encode(['success' => false, 'error' => $e->getMessage()]);
}
