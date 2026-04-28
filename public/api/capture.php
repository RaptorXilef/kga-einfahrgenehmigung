<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * API-Endpunkt für PayPal Capture v0.9.5
 *
 * @file public/api/capture.php
 */

declare(strict_types=1);

/**
 * API-Endpunkt für PayPal Capture v0.9.5
 */
$appRoot = (function (): string {
    $dir = __DIR__;
    // Suche nach oben, bis vendor/autoload.php gefunden wird
    while ($dir !== \dirname($dir)) {
        if (\file_exists($dir . '/vendor/autoload.php')) {
            return $dir;
        }
        $dir = \dirname($dir);
    }

    // Fallback für spezielle Unterordner-Strukturen
    return \dirname(__DIR__, 2);
})();

require_once $appRoot . '/vendor/autoload.php';

use App\Application\PaymentController;
use App\Bootstrap\Container;
use App\Infrastructure\Config\Config;

$settings              = require_once $appRoot . '/config/config.php';
$settings['root_path'] = $appRoot;

$container  = new Container(new Config($settings));
$controller = $container->get(PaymentController::class);

$controller->handleCapture();
