<?php

/**
 * API: Abschluss für Überweisungen v0.12.0
 * Überführt den Antrag in die Hauptdatenbank (Status: wartend).
 *
 * @file public/api/finalize_wire.php
 */

declare(strict_types=1);

$appRoot = (function (): string {
    $dir = __DIR__;
    while ($dir !== \dirname($dir)) {
        if (\file_exists($dir . '/vendor/autoload.php')) {
            return $dir;
        }
        $dir = \dirname($dir);
    }

    return \dirname(__DIR__, 2);
})();

require_once $appRoot . '/vendor/autoload.php';

use App\Bootstrap\Container;
use App\Core\Service\PermitService;
use App\Infrastructure\Config\Config;

\header('Content-Type: application/json');

// Token aus der URL holen
$token = (string) ($_GET['token'] ?? '');

if ($token === '') {
    echo \json_encode(['success' => false, 'error' => 'Kein Token angegeben']);
    exit;
}

$settings              = require_once $appRoot . '/config/config.php';
$settings['root_path'] = $appRoot;

$container = new Container(new Config($settings));
$service   = $container->get(PermitService::class);

try {
    // Verschiebt Daten von verified_pending.json nach daten.json
    // und triggert den Mail-Versand an Nutzer & Vorstand
    $permit = $service->finaliseRequest($token, 'wartend', 'Zahlung per Überweisung gewählt');

    echo \json_encode([
        'success' => true,
        'code'    => $permit->code,
    ]);
} catch (\Exception $e) {
    \http_response_code(400);
    echo \json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ]);
}
