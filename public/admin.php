<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * Admin-Oberfläche für den Vorstand.
 *
 * @file      public/admin.php
 *
 * @since     0.1.0
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
use App\Contracts\Storage\StorageInterface;
use App\Core\Service\PermitService;
use App\Infrastructure\Config\Config;
use App\Infrastructure\Storage\JsonStorage;
use App\Infrastructure\Storage\MySqlStorage;

// 1. Konfiguration laden (die alte config.php gibt nun einfach ein Array zurück)
$settings = require_once $appRoot . '/config.php';

// Wir injizieren den Root-Pfad in die Config, damit alle Services ihn kennen
$settings['root_path'] = $appRoot;

$container     = new Container(new Config($settings));
$permitService = $container->get(PermitService::class);
$storage       = $container->get(StorageInterface::class); // Aktueller Standard-Speicher

$message = '';

// A. Manuelle Freischaltung via Link
if (isset($_GET['code'], $_GET['token'])) {
    $expectedToken = \hash('sha256', $_GET['code'] . $settings['geheimnis']);
    if (\hash_equals($expectedToken, $_GET['token']) && $permitService->manualActivate($_GET['code'])) {
        $message = "Genehmigung {$_GET['code']} wurde manuell aktiviert!";
    }
}

// B. Migration (Simpel skizziert)
if (isset($_POST['migrate'])) {
    // Hier erstellen wir kurzzeitig beide Instanzen zum Umziehen
    $json  = new JsonStorage($settings['storage_path']);
    $mysql = new MySqlStorage(new PDO($settings['db_dsn'], $settings['db_user'], $settings['db_pass']));

    $count = $_POST['direction'] === 'to_mysql'
        ? $json->migrateTo($mysql)
        : $mysql->migrateTo($json);

    $message = "Migration abgeschlossen. $count Datensätze übertragen.";
}

include $appRoot . '/templates/pages/admin_view.phtml';
