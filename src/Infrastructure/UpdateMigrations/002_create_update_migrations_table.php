<?php

declare(strict_types=1);

return function (?\PDO $pdo, \App\Contracts\Config\ConfigInterface $config): void {

    if ($pdo instanceof \PDO) {
        try {
            // Legt die Tabelle sicher an, falls sie (z.B. bei Bestandskunden) noch fehlt
            $sql = 'CREATE TABLE IF NOT EXISTS `update_migrations` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `version` VARCHAR(50) NOT NULL,
                `executed_at` DATETIME NOT NULL,
                UNIQUE KEY `idx_version` (`version`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';

            $pdo->exec($sql);
        } catch (\PDOException $e) {
            \error_log('Migration 002 (MySQL Create Table): ' . $e->getMessage());
        }
    }

};
