<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * API-Endpunkt für PayPal Capture mit Anker-System.
 *
 * @file      public/api/capture.php
 * @since     0.1.0
 */

declare(strict_types=1);

header('Content-Type: application/json');

// --- ANKER-SYSTEM ---
// Wir gehen zwei Ebenen hoch (api -> public -> root) und suchen den core-ordner
$appRoot = dirname(__DIR__, 2) . '/kga-core';

if (!file_exists($appRoot . '/vendor/autoload.php')) {
    // Fallback: Falls du lokal doch alles im Root hast
    $appRoot = dirname(__DIR__, 2);
}
// --------------------

require_once $appRoot . '/vendor/autoload.php';

use App\Bootstrap\Container;
use App\Infrastructure\Config\Config;
use App\Core\Service\PermitService;

try {
    $settings = require_once $appRoot . '/config.php';
    $settings['root_path'] = $appRoot; // Pfad injizieren

    $container = new Container(new Config($settings));
    $permitService = $container->get(PermitService::class);

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!isset($data['orderID'], $data['permitCode'])) {
        throw new Exception("Fehlende Parameter.");
    }

    $success = $permitService->completePayment(
        (string)$data['permitCode'],
        (string)$data['orderID']
    );

    echo json_encode([
        'success' => $success,
        'message' => $success ? 'Zahlung verarbeitet' : 'Fehler bei Verifizierung'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
