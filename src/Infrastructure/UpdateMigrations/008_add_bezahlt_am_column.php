<?php

declare(strict_types=1);

use App\Contracts\Config\ConfigInterface;

/**
 * Fügt die Spalte "erstellt" in permits und permits_archiv, sollte diese fehlen
 */
return function (?\PDO $pdo, ConfigInterface $config): void {
    if ($pdo instanceof \PDO) {
        $permitsTable = $config->get('storage_config')['permits']['table'] ?? 'permits';
        $archiveTable = $config->get('storage_config')['permits_archive']['table'] ?? 'permits_archive';
        $tables       = [$permitsTable, $archiveTable];

        foreach ($tables as $table) {
            try {
                // Fügt die neue Spalte "bezahlt_am" sicher nach dem Erstellungsdatum ein
                $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `bezahlt_am` DATETIME NULL DEFAULT NULL AFTER `erstellt`");
            } catch (\PDOException $e) {
                // Fehler 1060 bedeutet: Spalte existiert bereits (Duplikat). Ignorieren wir laut Konvention.
                if (! \str_contains($e->getMessage(), '1060')) {
                    \error_log('Migration 008 (MySQL): ' . $e->getMessage());
                }
            }
        }
    }
};
