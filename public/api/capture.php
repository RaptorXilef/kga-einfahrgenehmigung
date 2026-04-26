<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * API-Endpunkt für PayPal Capture mit Anker-System.
 *
 * @file      public/api/capture.php
 *
 * @since     0.1.0
 */

declare(strict_types=1);

\header('Content-Type: application/json');

// --- ANKER-SYSTEM ---
// 1. Versuch: Wir sind in /public/ oder /public/api/ und suchen /kga-core/ im Root
$appRoot = \dirname(__DIR__, 1) . '/kga-core';

// 2. Versuch: Spezialfall Strato/Ionos (falls /public/ sehr tief verschachtelt ist)
if (! \file_exists($appRoot . '/vendor/autoload.php')) {
    $appRoot = \dirname(__DIR__, 2) . '/kga-core';
}

// 3. Letzter Rettungsanker: Direkte Suche (nur falls 1 & 2 fehlschlagen)
if (! \file_exists($appRoot . '/vendor/autoload.php')) {
    $appRoot = __DIR__ . '/../kga-core';
}
// --------------------

use App\Bootstrap\Container;
use App\Core\Service\PermitService;
use App\Infrastructure\Config\Config;

try {
    $settings              = require_once $appRoot . '/config.php';
    $settings['root_path'] = $appRoot; // Pfad injizieren

    $container     = new Container(new Config($settings));
    $permitService = $container->get(PermitService::class);

    $json = \file_get_contents('php://input');
    $data = \json_decode($json, true);

    if (! isset($data['orderID'], $data['permitCode'])) {
        throw new Exception('Fehlende Parameter.');
    }

    $success = $permitService->completePayment(
        (string) $data['permitCode'],
        (string) $data['orderID'],
    );

    echo \json_encode([
        'success' => $success,
        'message' => $success ? 'Zahlung verarbeitet' : 'Fehler bei Verifizierung',
    ]);
} catch (Exception $e) {
    \http_response_code(400);
    echo \json_encode(['success' => false, 'error' => $e->getMessage()]);
}
