<?php

declare(strict_types=1);

/**
 * PayPal Order Erstellung für bereits verifizierte Anträge v0.11.2
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
use App\Contracts\Payment\PaymentProviderInterface;
use App\Contracts\Storage\StorageInterface;
use App\Infrastructure\Config\Config;

\header('Content-Type: application/json');

$code = (string) ($_GET['code'] ?? '');

if ($code === '') {
    echo \json_encode(['success' => false, 'error' => 'Kein Code angegeben']);
    exit;
}

$settings              = require_once $appRoot . '/config/config.php';
$settings['root_path'] = $appRoot;

$container = new Container(new Config($settings));
$storage   = $container->get(StorageInterface::class);
$payment   = $container->get(PaymentProviderInterface::class);

$permit = $storage->findByHash($code);

if (! $permit) {
    echo \json_encode(['success' => false, 'error' => 'Antrag nicht gefunden']);
    exit;
}

if ($permit->status === 'bezahlt') {
    echo \json_encode(['success' => false, 'error' => 'Bereits bezahlt']);
    exit;
}

// PayPal Order mit dem im Permit gespeicherten Preis erstellen
$orderId = $payment->createOrder($permit->preisSnapshot);

if (! $orderId) {
    echo \json_encode(['success' => false, 'error' => 'PayPal API Fehler']);
    exit;
}

echo \json_encode(['id' => $orderId]);
