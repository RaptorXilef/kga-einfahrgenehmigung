<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * Haupteinstiegspunkt der Anwendung.
 *
 * Initialisiert die Umgebung und delegiert Anfragen an den PermitService.
 * Trennt Request-Handling von der Geschäftslogik.
 *
 * @file      public/index.php
 *
 * @since     0.1.0 - refactor(app): Umstellung auf Container-basiertes Bootstrapping.
 * @since     0.4.0 - refactor(arch): Move to root structure and blueprint tools.
 */

declare(strict_types=1);

/**
 * FLEXIBLES ANKER-SYSTEM
 * Sucht den Projekt-Root (wo vendor/ liegt) ausgehend vom aktuellen Verzeichnis.
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

use App\Bootstrap\Container;
use App\Core\Service\PermitService;
use App\Infrastructure\Config\Config;

// 1. Konfiguration laden (die alte config.php gibt nun einfach ein Array zurück)
// Wir injizieren darunter den Root-Pfad in die Config, damit alle Services ihn kennen
$settings              = require_once $appRoot . '/config/config.php';
$settings['root_path'] = $appRoot;
$container             = new Container(new Config($settings));

/** @var PermitService $permitService */
$permitService = $container->get(PermitService::class);

$message = '';
$success = false;

// 2. Formular-Verarbeitung (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $permit  = $permitService->createPermit($_POST);
        $success = true;
        // Wichtig: (string) Cast nur wenn email ein Objekt wäre, hier ist es ein String
        $message = 'Antrag erfolgreich! Die Genehmigung wurde an ' . \htmlspecialchars((string) $permit->email) . ' gesendet.';
    } catch (\Exception $e) { // Backslash vor Exception, da globaler PHP-Namespace
        $message = 'Fehler: ' . $e->getMessage();
    }
}

// Config für das Template bereitstellen (Zwecke, Preise etc.)
$config = $container->get(Config::class);
include $appRoot . '/templates/pages/formular.phtml';
