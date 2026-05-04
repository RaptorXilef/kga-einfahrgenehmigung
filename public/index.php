<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * Haupteinstiegspunkt der Anwendung.
 *
 * Initialisiert die Umgebung und delegiert Anfragen an den PermitService.
 * Trennt Request-Handling von der Geschäftslogik.
 *
 * @file      public/index.php
 */

declare(strict_types=1);

/**
 * Haupteinstiegspunkt v0.9.5
 */
$appRoot = (function (): string {
    $dir = __DIR__;
    // Wir suchen nach oben, bis wir den Ordner finden, der 'vendor' oder 'src' enthält
    while ($dir !== \dirname($dir)) {
        if (\file_exists($dir . '/vendor/autoload.php')) {
            return $dir;
        }
        $dir = \dirname($dir);
    }

    // Fallback: Falls nichts gefunden wurde, gehen wir eine Ebene hoch
    return \dirname(__DIR__);
})();

require_once $appRoot . '/vendor/autoload.php';

use App\Application\PermitController;
use App\Bootstrap\Container;
use App\Infrastructure\Config\Config;

// 1. Konfiguration laden (die alte config.php gibt nun einfach ein Array zurück)
// Wir injizieren darunter den Root-Pfad in die Config, damit alle Services ihn kennen
$settings              = require_once $appRoot . '/config/config.php';
$settings['root_path'] = $appRoot;

$container  = new Container(new Config($settings));
$controller = $container->get(PermitController::class);

// POST und GET übergeben
$controller->handleRequest($_POST, $_GET);
