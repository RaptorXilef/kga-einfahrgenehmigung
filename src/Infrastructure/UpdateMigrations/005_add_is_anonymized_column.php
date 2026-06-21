<?php

declare(strict_types=1);

use App\Contracts\Config\ConfigInterface;

return function (?\PDO $pdo, ConfigInterface $config): void {
    if ($pdo instanceof \PDO) {
        $archiveTable = $config->get('storage_config')['permits_archive']['table'] ?? 'permits_archive';

        try {
            // 1. DSGVO-Spalte zur Archiv-Tabelle hinzufügen
            $pdo->exec("ALTER TABLE `{$archiveTable}` ADD COLUMN `is_anonymized` TINYINT(1) NOT NULL DEFAULT 0");

            // 2. Index hinzufügen, damit der tägliche Cronjob rasendschnell suchen kann
            $pdo->exec("ALTER TABLE `{$archiveTable}` ADD INDEX `idx_anonymized` (`is_anonymized`)");

        } catch (\PDOException $e) {
            // 1060 = Spalte existiert bereits | 1061 = Index existiert bereits
            if (! \str_contains($e->getMessage(), '1060') && ! \str_contains($e->getMessage(), '1061')) {
                \error_log('Migration 005 (MySQL): ' . $e->getMessage());
            }
        }
    }
};
