<?php

/**
 * Pächter-Verlauf Einstiegspunkt v0.13.0
 * Ermöglicht die Einsicht aller Genehmigungen via Magic Link.
 *
 * @file public/history.php
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

use App\Application\HistoryController;
use App\Bootstrap\Container;
use App\Infrastructure\Config\Config;

// Session für den Magic-Link-Login
if (\session_status() === \PHP_SESSION_NONE) {
    \session_start();
}

$settings              = require_once $appRoot . '/config/config.php';
$settings['root_path'] = $appRoot;

$container  = new Container(new Config($settings));
$controller = $container->get(HistoryController::class);

$controller->handleRequest($_GET, $_POST);
