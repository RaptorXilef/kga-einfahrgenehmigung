<?php

declare(strict_types=1);

use App\Contracts\Config\ConfigInterface;

return function (?\PDO $pdo, ConfigInterface $config): void {
    if ($pdo instanceof \PDO) {
        // Lese die Tabellennamen (inklusive deines geänderten Namens)
        $permitsTable   = $config->get('storage_config')['permits']['table'] ?? 'permits';
        $archiveTable   = $config->get('storage_config')['permits_archive']['table'] ?? 'permits_archive';
        $cancelledTable = $config->get('storage_config')['permits_cancelled']['table'] ?? 'permits_cancelled';

        $tables = [$permitsTable, $archiveTable, $cancelledTable];

        foreach ($tables as $table) {
            try {
                $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `reminder_sent` TINYINT(1) NOT NULL DEFAULT 0");
            } catch (\PDOException $e) {
                // Fehler 1060 bedeutet "Duplicate column name" - Spalte existiert also schon
                if (! \str_contains($e->getMessage(), '1060')) {
                    \error_log('Migration 011 (MySQL): ' . $e->getMessage());
                }
            }
        }
    }
};
