<?php

declare(strict_types=1);

use App\Contracts\Config\ConfigInterface;

return function (?\PDO $pdo, ConfigInterface $config): void {
    if ($pdo instanceof \PDO) {
        // Tabellennamen dynamisch aus der Config holen
        $permitsTable = $config->get('storage_config')['permits']['table'] ?? 'permits';
        $archiveTable = $config->get('storage_config')['permits_archive']['table'] ?? 'permits_archive';

        $tables = [$permitsTable, $archiveTable];

        foreach ($tables as $table) {
            try {
                // Spalte hinzufügen
                $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `agreements` JSON NULL");
            } catch (\PDOException $e) {
                // Fehlercode 1060 bedeutet: "Duplicate column name" (Spalte existiert bereits).
                // Das ist okay, dann ignorieren wir den Fehler einfach.
                if (! \str_contains($e->getMessage(), '1060')) {
                    \error_log('Migration 004 (MySQL): ' . $e->getMessage());
                }
            }
        }
    }
};
