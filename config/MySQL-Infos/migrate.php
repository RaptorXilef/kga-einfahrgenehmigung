<?php
// migrate.php
$container = require_once __DIR__ . '/src/Bootstrap/app.php';

// Wir laden manuell beide
$jsonStorage = new \App\Infrastructure\Storage\JsonStorage(__DIR__ . '/storage/daten.json');
// Hier müsstest du die PDO Verbindung manuell kurz aufbauen für MySqlStorage

// $count = $jsonStorage->migrateTo($mysqlStorage);
// echo "Erfolgreich $count Datensätze nach MySQL migriert!";
