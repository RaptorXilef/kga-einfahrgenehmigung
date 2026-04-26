<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * Haupteinstiegspunkt der Anwendung.
 *
 * Initialisiert die Umgebung und delegiert Anfragen an den PermitService.
 * Trennt Request-Handling von der Geschäftslogik.
 *
 * @file      index.php
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
$settings = require_once $appRoot . '/config.php';

// Wir injizieren den Root-Pfad in die Config, damit alle Services ihn kennen
$settings['root_path'] = $appRoot;

$container = new Container(new Config($settings));

/** @var PermitService $permitService */
$permitService = $container->get(PermitService::class);

$message = '';
$success = false;

// 2. Formular-Verarbeitung (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['method'])) {
    try {
        if ($_POST['method'] === 'bank') {
            $permit  = $permitService->createPendingPermit($_POST);
            $success = true;
            $message = 'Antrag erfolgreich erstellt! Ihr Code lautet: ' . $permit->code;
        }
    } catch (Exception $e) {
        $message = 'Fehler: ' . $e->getMessage();
    }
}

// 3. View laden (PHTML-Template für das UI)
// Wir trennen HTML von PHP -> Separation of Concerns
include $appRoot . '/templates/pages/formular.phtml';
