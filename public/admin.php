<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * Admin-Oberfläche für den Vorstand.
 *
 * @file      public/admin.php
 *
 * @since     0.1.0
 */

declare(strict_types=1);

// ==========================================================
// DIE EINZIGE VARIABLE FÜR PFADE:
// Zeige hier auf den Ordner, der 'src', 'vendor' etc. enthält.
// ==========================================================
$appRoot = dirname(__DIR__, 1); // Standard: Eine Ebene höher als 'public'

// Ab hier ist alles dynamisch:
require_once $appRoot . '/vendor/autoload.php';

use App\Bootstrap\Container;
use App\Infrastructure\Config\Config;
use App\Core\Service\PermitService;
use App\Contracts\Storage\StorageInterface;
use App\Infrastructure\Storage\MySqlStorage;
use App\Infrastructure\Storage\JsonStorage;

// 1. Konfiguration laden (die alte config.php gibt nun einfach ein Array zurück)
$settings = require_once $appRoot . '/config.php';

// Wir injizieren den Root-Pfad in die Config, damit alle Services ihn kennen
$settings['root_path'] = $appRoot;

$container = new Container(new Config($settings));
$permitService = $container->get(PermitService::class);
$storage = $container->get(StorageInterface::class); // Aktueller Standard-Speicher

$message = "";

// A. Manuelle Freischaltung via Link
if (isset($_GET['code'], $_GET['token'])) {
    $expectedToken = hash('sha256', $_GET['code'] . $settings['geheimnis']);
    if (hash_equals($expectedToken, $_GET['token'])) {
        if ($permitService->manualActivate($_GET['code'])) {
            $message = "Genehmigung {$_GET['code']} wurde manuell aktiviert!";
        }
    }
}

// B. Migration (Simpel skizziert)
if (isset($_POST['migrate'])) {
    // Hier erstellen wir kurzzeitig beide Instanzen zum Umziehen
    $json = new JsonStorage($settings['storage_path']);
    $mysql = new MySqlStorage(new PDO($settings['db_dsn'], $settings['db_user'], $settings['db_pass']));

    $count = $_POST['direction'] === 'to_mysql'
        ? $json->migrateTo($mysql)
        : $mysql->migrateTo($json);

    $message = "Migration abgeschlossen. $count Datensätze übertragen.";
}

include $appRoot . '/templates/pages/admin_view.phtml';
