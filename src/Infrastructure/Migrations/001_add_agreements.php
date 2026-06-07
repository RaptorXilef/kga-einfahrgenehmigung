<?php

declare(strict_types=1);

// TODO DOCBLOCK

// src/Infrastructure/Migrations/001_add_agreements.php
return function (?\PDO $pdo, \App\Contracts\Config\ConfigInterface $config): void {

    // Beispiel: Wenn MySQL aktiv ist, erweitere eine Tabelle
    if ($pdo) {
        try {
            $pdo->exec('ALTER TABLE `permits` ADD `agreements` JSON NULL');
        } catch (\PDOException $e) {
            // Ignorieren, falls die Spalte schon existiert
        }
    }

    // Ich kann hier auch JSON-Dateien einlesen und umschreiben, falls $pdo null ist!
};
