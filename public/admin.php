<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * Admin Einstiegspunkt v0.9.5
 *
 * @file      public/admin.php
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

    return \dirname(__DIR__);
})();

require_once $appRoot . '/vendor/autoload.php';

use App\Application\AdminController;
use App\Bootstrap\Container;
use App\Infrastructure\Config\Config;

$settings              = require_once $appRoot . '/config/config.php';
$settings['root_path'] = $appRoot;

$container  = new Container(new Config($settings));
$controller = $container->get(AdminController::class);

// Delegiere alles an die beweisbare Klasse
$controller->handleRequest($_GET, $_POST);
