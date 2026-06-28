<?php

declare(strict_types=1);

use App\Contracts\Config\ConfigInterface;

/**
 * Entfernt Einstellungen, die von storage.php in storage.json wandern, aus der PHP Datei
 * und fügt sie in die JSON ein.
 * Dabei werden ALLE vorhandenen Einstellungen 1:1 dynamisch übernommen!
 */
return function (?\PDO $pdo, ConfigInterface $config): void {
    $appRoot           = \rtrim((string) $config->get('root_path'), '/\\');
    $legacyStoragePath = $appRoot . '/config/storage.php';
    $jsonStoragePath   = $appRoot . '/storage/settings/storage.json';

    // Führe Migration nur aus, wenn die alte PHP-Datei existiert, aber die neue JSON noch nicht
    if (\file_exists($legacyStoragePath) && ! \file_exists($jsonStoragePath)) {
        $legacyData = require $legacyStoragePath;

        if (\is_array($legacyData)) {
            // 1. Datenbank-Einstellungen sicher extrahieren (oder Defaults setzen)
            $dbConfig = $legacyData['database'] ?? [
                'enabled' => false,
                'host'    => 'localhost',
                'port'    => '',
                'dbname'  => 'kga_einfahrts_manager',
                'user'    => 'root',
                'pass'    => '',
                'charset' => 'utf8mb4',
            ];

            // 2. Datenbank aus den Legacy-Daten restlos entfernen
            unset($legacyData['database']);

            // 3. JSON-Datenstruktur aufbauen: Meta-Tag an den Anfang, dann alle RESTLICHEN Daten übernehmen
            $jsonData = \array_merge(
                ['_meta' => 'AUTO-GENERATED JSON CONFIG FROM LEGACY PHP FILE'],
                $legacyData,
            );

            // Sicherheitshalber sicherstellen, dass die in Phase 4 erstellte Tabelle dabei ist
            if (! isset($jsonData['storage_config']['cancelled_permits'])) {
                // Den Typ von 'permits' erben, falls vorhanden (Fallback: json)
                $inheritedType = $jsonData['storage_config']['permits']['type'] ?? 'json';

                $jsonData['storage_config']['cancelled_permits'] = [
                    'type'  => $inheritedType,
                    'table' => 'cancelled_permits',
                    'file'  => 'cancelled_permits.json',
                ];
            }

            // JSON-Datei schreiben
            $settingsDir = \dirname($jsonStoragePath);
            if (! \is_dir($settingsDir)) {
                @\mkdir($settingsDir, 0o755, true);
            }
            \file_put_contents($jsonStoragePath, \json_encode($jsonData, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE), \LOCK_EX);

            // 4. Werte sicher escapen für den PHP-String
            $enabled = $dbConfig['enabled'] ? 'true' : 'false';
            $host    = \addslashes((string) $dbConfig['host']);
            $port    = \addslashes((string) ($dbConfig['port'] ?? ''));
            $dbname  = \addslashes((string) $dbConfig['dbname']);
            $user    = \addslashes((string) $dbConfig['user']);
            $pass    = \addslashes((string) $dbConfig['pass']);
            $charset = \addslashes((string) $dbConfig['charset']);

            // 5. Die config/storage.php überschreiben (nur mit den DB-Daten)
            $phpContent = <<<PHP
                <?php
                declare(strict_types=1);

                // HINWEIS: Diese Datei enthaelt nur noch die sicheren Datenbank-Zugangsdaten.
                // Alle weiteren Storage-Einstellungen liegen nun in: storage/settings/storage.json

                return [
                    'database' => [
                        'enabled' => {$enabled},
                        'host'    => '{$host}',
                        'port'    => '{$port}',
                        'dbname'  => '{$dbname}',
                        'user'    => '{$user}',
                        'pass'    => '{$pass}',
                        'charset' => '{$charset}',
                    ],
                ];
                PHP;

            \file_put_contents($legacyStoragePath, $phpContent, \LOCK_EX);
        }
    }
};
