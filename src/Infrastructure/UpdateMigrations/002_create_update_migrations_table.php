<?php

declare(strict_types=1);

use App\Contracts\Config\ConfigInterface;

return function (?\PDO $pdo, ConfigInterface $config): void {
    if ($pdo instanceof \PDO) {
        $cfg = $config->get('storage_config')['update_migrations'] ?? null;
        if (! $cfg) {
            return;
        }

        $table = $cfg['table'];

        // Legt die Tabelle sicher an, falls sie (z.B. bei Bestandskunden) noch fehlt
        // ID von INT AUTO_INCREMENT auf VARCHAR(50) umgestellt,
        // damit die aus der JSON-Welt kommenden String-UIDs (mig_...) nicht das SQL-Schema crashen.
        try {
            $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
                `id` VARCHAR(50) PRIMARY KEY,
                `version` VARCHAR(50) NOT NULL,
                `executed_at` DATETIME NOT NULL,
                UNIQUE KEY `idx_version` (`version`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

            $pdo->exec($sql);
        } catch (\PDOException $e) {
            \error_log('Migration 002 (MySQL Create Table): ' . $e->getMessage());
        }
    }
};
