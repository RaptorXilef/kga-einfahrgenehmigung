<?php

declare(strict_types=1);

use App\Contracts\Config\ConfigInterface;

/**
 * Ergänzt fehlende Spalten in permits_archive und permits_cancelled die beim letzten großen refactoring
 * vergessen wurden
 */
return function (?\PDO $pdo, ConfigInterface $config): void {
    if ($pdo instanceof \PDO) {
        $archiveTable   = $config->get('storage_config')['permits_archive']['table'] ?? 'permits_archive';
        $cancelledTable = $config->get('storage_config')['permits_cancelled']['table'] ?? 'permits_cancelled';

        foreach ([$archiveTable, $cancelledTable] as $table) {
            try {
                $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `is_suspended` TINYINT(1) NOT NULL DEFAULT 0");
            } catch (\PDOException $e) {
                if (! \str_contains($e->getMessage(), '1060')) { // 1060 = Duplicate column name
                    \error_log('Migration 013 (MySQL): ' . $e->getMessage());
                }
            }

            try {
                $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `suspension_reason` TEXT DEFAULT NULL");
            } catch (\PDOException $e) {
                if (! \str_contains($e->getMessage(), '1060')) {
                    \error_log('Migration 013 (MySQL): ' . $e->getMessage());
                }
            }
        }
    }
};
