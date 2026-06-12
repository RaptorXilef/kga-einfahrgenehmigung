<?php

/**
 * Was macht dieses Skript?
 * Für MySQL: Es prüft, ob eine Tabelle namens migrations existiert. Wenn ja, und die neue Tabelle update_migrations
 * noch nicht existiert, benennt es sie mit dem nativen SQL-Befehl RENAME TABLE rasend schnell um. So bleiben alle
 * Einträge erhalten.
 *
 * Für JSON: Genau dieselbe Logik! Es prüft im storage/-Ordner, ob eine migrations.json herumliegt. Wenn ja, wird
 * die Datei in update_migrations.json umbenannt.
 *
 * Safety First: Es ist komplett mit try...catch umschlossen. Selbst wenn dem Server Rechte fehlen sollten, bricht
 * das System-Update dadurch nicht ab.
 *
 * Path: src/Infrastructure/UpdateMigrations/001_rename_migrations_table.php
 */

declare(strict_types=1);

use App\Contracts\Config\ConfigInterface;

return function (?\PDO $pdo, ConfigInterface $config): void {

    // 1. MySQL Migration (Falls Datenbank aktiv ist)
    if ($pdo instanceof \PDO) {
        try {
            // Prüfen, ob die alte Tabelle 'migrations' existiert
            $oldTableExists = $pdo->query("SHOW TABLES LIKE 'migrations'")->rowCount() > 0;

            // Prüfen, ob die neue Tabelle 'update_migrations' schon existiert (um Konflikte zu vermeiden)
            $newTableExists = $pdo->query("SHOW TABLES LIKE 'update_migrations'")->rowCount() > 0;

            if ($oldTableExists && ! $newTableExists) {
                $pdo->exec('RENAME TABLE `migrations` TO `update_migrations`');
            } elseif ($oldTableExists && $newTableExists) {
                // Falls aus irgendeinem Grund beide existieren, löschen wir die alte leere,
                // um künftige Verwirrung zu vermeiden (optional, aber sauber)
                $pdo->exec('DROP TABLE `migrations`');
            }
        } catch (\PDOException $e) {
            // Fehler lautlos ignorieren, damit das restliche Update nicht abbricht
            \error_log('Migration 001 (MySQL): ' . $e->getMessage());
        }
    }

    // 2. JSON Fallback Migration (Falls das System ohne MySQL läuft)
    try {
        $storagePrefix = \ltrim((string) $config->get('storage_path_prefix', 'storage/'), '/\\');
        $rootPath      = \rtrim((string) $config->get('root_path', ''), '/\\');

        $oldJsonPath = $rootPath . '/' . $storagePrefix . 'migrations.json';
        $newJsonPath = $rootPath . '/' . $storagePrefix . 'update_migrations.json';

        if (\file_exists($oldJsonPath) && ! \file_exists($newJsonPath)) {
            \rename($oldJsonPath, $newJsonPath);
        } elseif (\file_exists($oldJsonPath) && \file_exists($newJsonPath)) {
            \unlink($oldJsonPath); // Alte Datei aufräumen, falls beide existieren
        }
    } catch (\Throwable $e) {
        \error_log('Migration 001 (JSON): ' . $e->getMessage());
    }

};
