<?php

declare(strict_types=1);

/**
 * API: Überweisungs-Abschluss v0.12.1
 */
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
    // Überführt Daten von verified_pending.json nach daten.json
    $permit = $service->finaliseRequest($token, 'wartend', 'Zahlung per Überweisung gewählt');
    echo \json_encode(['success' => true, 'code' => $permit->code]);
} catch (\Exception $e) {
    echo \json_encode(['success' => false, 'error' => $e->getMessage()]);
}
