<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

declare(strict_types=1);

/**
 * API-Endpunkt für vorläufige Genehmigungen v0.9.5
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

use App\Application\PaymentController;
use App\Bootstrap\Container;
use App\Infrastructure\Config\Config;

$settings              = require_once $appRoot . '/config/config.php';
$settings['root_path'] = $appRoot;

$container  = new Container(new Config($settings));
$controller = $container->get(PaymentController::class);

$controller->handleCreatePending($_POST);
