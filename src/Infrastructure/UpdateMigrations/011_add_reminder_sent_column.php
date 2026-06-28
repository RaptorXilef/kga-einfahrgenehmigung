<?php

declare(strict_types=1);

use App\Contracts\Config\ConfigInterface;

return function (?\PDO $pdo, ConfigInterface $config): void {
    if ($pdo instanceof \PDO) {
        $storageConfig = $config->get('storage_config', []);

        // SICHRE ARRAY-ABFRAGE (Fix für den "Beim 1. Mal Absturz"-Bug)
        $permitsTable   = $storageConfig['permits']['table'] ?? 'permits';
        $archiveTable   = $storageConfig['permits_archive']['table'] ?? 'permits_archive';
        $cancelledTable = $storageConfig['permits_cancelled']['table'] ?? 'permits_cancelled';

        $tables = [$permitsTable, $archiveTable, $cancelledTable];

        // 1. SPALTE ZU ALLEN 3 TABELLEN HINZUFÜGEN
        foreach ($tables as $table) {
            // 1. Spalte hinzufügen
            try {
                $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `reminder_sent` TINYINT(1) NOT NULL DEFAULT 0");
            } catch (\PDOException $e) {
                // Fehler 1060: Spalte existiert bereits
                if (! \str_contains($e->getMessage(), '1060')) {
                    \error_log("Migration 011 (MySQL) Column Error in $table: " . $e->getMessage());
                }
            }
        }

        // 2. INDEX NUR ZUR AKTIVEN PERMITS-TABELLE HINZUFÜGEN
        try {
            $pdo->exec("ALTER TABLE `{$permitsTable}` ADD INDEX `idx_reminder_sent` (`reminder_sent`)");
        } catch (\PDOException $e) {
            // Fehler 1061: Indexname existiert bereits
            if (! \str_contains($e->getMessage(), '1061')) {
                \error_log("Migration 011 (MySQL) Index Error in {$permitsTable}: " . $e->getMessage());
            }
        }
    }
};
