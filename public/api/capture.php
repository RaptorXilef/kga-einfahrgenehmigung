<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * API-Endpunkt für PayPal Capture.
 *
 * Nimmt die OrderID entgegen und stößt die serverseitige Verifizierung an.
 *
 * @file      public/api/capture.php
 *
 * @since     0.1.0
 */

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Bootstrap\Container;
use App\Infrastructure\Config\Config;
use App\Core\Service\PermitService;

try {
    $settings = require_once __DIR__ . '/../../config.php';
    $container = new Container(new Config($settings));
    $permitService = $container->get(PermitService::class);

    // JSON Input lesen
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!isset($data['orderID'], $data['permitCode'])) {
        throw new Exception("Ungültige Anfrage-Daten.");
    }

    $success = $permitService->completePayment(
        (string)$data['permitCode'],
        (string)$data['orderID']
    );

    echo json_encode([
        'success' => $success,
        'message' => $success ? 'Zahlung verifiziert' : 'Verifizierung fehlgeschlagen',
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
