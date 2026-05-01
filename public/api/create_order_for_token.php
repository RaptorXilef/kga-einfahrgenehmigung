<?php

/**
 * PayPal Order Erstellung v0.14.0 - Nutzt den Preis-Snapshot
 *
 * @file public/api/create_order_for_token.php
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
use App\Contracts\Payment\PaymentProviderInterface;
use App\Infrastructure\Config\Config;

\header('Content-Type: application/json');

$token = (string) ($_GET['token'] ?? '');

if ($token === '') {
    echo \json_encode(['success' => false, 'error' => 'Kein Token angegeben']);
    exit;
}

$settings              = require_once $appRoot . '/config/config.php';
$settings['root_path'] = $appRoot;
$container             = new Container(new Config($settings));

// Warteraum laden
$verifiedPath = $appRoot . '/storage/verified_pending.json';
// FIX: Das @-Zeichen zur Fehlerunterdrückung ohne Backslash nutzen
// FIX: Long Ternary statt Short Ternary
$content     = \file_exists($verifiedPath) ? (string) \file_get_contents($verifiedPath) : '{}';
$allVerified = \json_decode($content, true) ?? [];
$tempRequest = $allVerified[$token] ?? null;

if (! $tempRequest) {
    echo \json_encode(['success' => false, 'error' => 'Antragssitzung nicht gefunden']);
    exit;
}

// FIX: Preis zwingend aus dem Snapshot nehmen
$payment = $container->get(PaymentProviderInterface::class);
$orderId = $payment->createOrder((float) $tempRequest['preisSnapshot']);

if (! $orderId) {
    echo \json_encode(['success' => false, 'error' => 'PayPal API Fehler']);
    exit;
}

echo \json_encode(['id' => $orderId]);
