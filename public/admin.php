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

// --- ANKER-SYSTEM ---
// 1. Versuch: Wir sind in /public/ oder /public/api/ und suchen /kga-core/ im Root
$appRoot = dirname(__DIR__, 1) . '/kga-core';

// 2. Versuch: Spezialfall Strato/Ionos (falls /public/ sehr tief verschachtelt ist)
if (!file_exists($appRoot . '/vendor/autoload.php')) {
    $appRoot = dirname(__DIR__, 2) . '/kga-core';
}

// 3. Letzter Rettungsanker: Direkte Suche (nur falls 1 & 2 fehlschlagen)
if (!file_exists($appRoot . '/vendor/autoload.php')) {
    $appRoot = __DIR__ . '/../kga-core';
}
// --------------------

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
