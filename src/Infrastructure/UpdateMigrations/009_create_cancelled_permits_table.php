<?php

declare(strict_types=1);

use App\Contracts\Config\ConfigInterface;

/**
 * Fügt die Fehlende neue Storniert Tabelle hinzu
 */
return function (?\PDO $pdo, ConfigInterface $config): void {
    if ($pdo instanceof \PDO) {
        $table = $config->get('storage_config')['cancelled_permits']['table'] ?? 'cancelled_permits';

        try {
            $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
                `code` VARCHAR(50) NOT NULL,
                `template_key` VARCHAR(50) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `email` VARCHAR(255) DEFAULT NULL,
                `kennzeichen` VARCHAR(20) DEFAULT NULL,
                `parzelle` VARCHAR(10) NOT NULL,
                `typ` VARCHAR(20) NOT NULL,
                `firma` VARCHAR(255) DEFAULT NULL,
                `zweck` VARCHAR(255) NOT NULL,
                `preis` DECIMAL(10,2) NOT NULL,
                `von` DATE NOT NULL,
                `bis` DATE NOT NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT 'storniert',
                `erstellt` DATETIME NOT NULL,
                `interner_kommentar` TEXT DEFAULT NULL,
                `is_anonymized` TINYINT(1) NOT NULL DEFAULT 1,
                `agreements` JSON DEFAULT NULL,
                `bezahlt_am` DATETIME DEFAULT NULL,
                PRIMARY KEY (`code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            $pdo->exec($sql);
        } catch (\PDOException $e) {
            \error_log('Migration 009 (MySQL): ' . $e->getMessage());
        }
    }
};
