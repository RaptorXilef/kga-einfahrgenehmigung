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
 * @since     0.1.0
 * - refactor(app): Umstellung auf Container-basiertes Bootstrapping.
 */

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Bootstrap\Container;
use App\Infrastructure\Config\Config;
use App\Core\Service\PermitService;

// 1. Konfiguration laden (die alte config.php gibt nun einfach ein Array zurück)
$settings = require_once __DIR__ . '/config.php';
$container = new Container(new Config($settings));

/** @var PermitService $permitService */
$permitService = $container->get(PermitService::class);

$message = '';
$success = false;

// 2. Formular-Verarbeitung (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['method'])) {
    try {
        if ($_POST['method'] === 'bank') {
            $permit = $permitService->createPendingPermit($_POST);
            $success = true;
            $message = "Antrag erfolgreich erstellt! Ihr Code lautet: " . $permit->code;
        }
    } catch (\Exception $e) {
        $message = "Fehler: " . $e->getMessage();
    }
}

// 3. View laden (PHTML-Template für das UI)
// Wir trennen HTML von PHP -> Separation of Concerns
include __DIR__ . '/templates/pages/formular.phtml';
