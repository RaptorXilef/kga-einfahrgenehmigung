<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

/**
 * TODO mysql schema
 * TODO sql schema anpassen
 */
final class SchemaRegistry
{
    public static function getSchemas(): array
    {
        return [
            'groups' => 'CREATE TABLE IF NOT EXISTS `groups` (
                `id` VARCHAR(50) PRIMARY KEY,
                `name` VARCHAR(100) NOT NULL,
                `permissions` JSON
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',

            'users' => 'CREATE TABLE IF NOT EXISTS `users` (
                `id` VARCHAR(50) PRIMARY KEY,
                `username` VARCHAR(50) NOT NULL,
                `group` VARCHAR(50) NOT NULL,
                `pass` VARCHAR(255) NOT NULL,
                UNIQUE KEY `idx_username` (`username`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',

            'vouchers' => 'CREATE TABLE IF NOT EXISTS `vouchers` (
                `code` VARCHAR(50) PRIMARY KEY,
                `reason` VARCHAR(255),
                `template_key` VARCHAR(50),
                `type` VARCHAR(20),
                `value` DECIMAL(10,2),
                `multi_use` TINYINT(1), -- VORHER: multiUse
                `max_uses` INT, -- VORHER: maxUses
                `uses_count` INT DEFAULT 0,
                `expires_at` DATETIME NULL, -- VORHER: expiresAt
                `date_mode` VARCHAR(20), -- VORHER: dateMode
                `created_by` VARCHAR(50), -- VORHER: createdBy
                `created_at` DATETIME,
                `status` VARCHAR(20) DEFAULT \'aktiv\',
                `data` JSON,
                INDEX `idx_voucher_validity` (`expires_at`, `uses_count`, `max_uses`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',

            'permits' => 'CREATE TABLE IF NOT EXISTS `permits` (
                `code` VARCHAR(50) NOT NULL,
                `template_key` VARCHAR(50) NOT NULL,  -- VORHER: templateKey
                `name` VARCHAR(255) NOT NULL,
                `email` VARCHAR(255) DEFAULT NULL,
                `kennzeichen` VARCHAR(20) DEFAULT NULL,
                `parzelle` VARCHAR(10) NOT NULL,
                `typ` VARCHAR(20) NOT NULL,
                `firma` VARCHAR(255) DEFAULT NULL,
                `zweck` VARCHAR(255) NOT NULL,
                `preis` DECIMAL(10,2) NOT NULL, -- VORHER: preisSnapshot
                `von` DATE NOT NULL,
                `bis` DATE NOT NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT \'offen\',
                `is_suspended` TINYINT(1) NOT NULL DEFAULT 0, -- VORHER: isSuspended
                `suspension_reason` TEXT DEFAULT NULL, -- VORHER: suspensionReason
                `erstellt` DATETIME NOT NULL,
                `interner_kommentar` TEXT DEFAULT NULL, -- VORHER: internerKommentar
                `agreements` JSON DEFAULT NULL, -- NEU: Zustimmungen (DSGVO, AGB, etc.)
                `bezahlt_am` DATETIME DEFAULT NULL,
                PRIMARY KEY (`code`),
                INDEX `idx_kennzeichen` (`kennzeichen`),
                INDEX `idx_parzelle` (`parzelle`),
                INDEX `idx_email` (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',

            'permits_archive' => 'CREATE TABLE IF NOT EXISTS `permits_archive` (
                `code` VARCHAR(50) NOT NULL,
                `template_key` VARCHAR(50) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `email` VARCHAR(255) DEFAULT NULL,
                `kennzeichen` VARCHAR(20) DEFAULT NULL,
                `parzelle` VARCHAR(10) NOT NULL,
                `typ` VARCHAR(20) NOT NULL,
                `firma` VARCHAR(255) DEFAULT NULL,
                `zweck` VARCHAR(255) NOT NULL,
                `preis` DECIMAL(10,2) NOT NULL, -- VORHER: preisSnapshot
                `von` DATE NOT NULL,
                `bis` DATE NOT NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT \'offen\',
                `erstellt` DATETIME NOT NULL,
                `interner_kommentar` TEXT DEFAULT NULL, -- VORHER: internerKommentar
                `is_anonymized` TINYINT(1) NOT NULL DEFAULT 0, -- NEU: DSGVO-Flag
                `agreements` JSON DEFAULT NULL, -- NEU: Zustimmungen im Archiv speichern
                `bezahlt_am` DATETIME DEFAULT NULL,
                PRIMARY KEY (`code`),
                INDEX `idx_kennzeichen` (`kennzeichen`),
                INDEX `idx_anonymized` (`is_anonymized`), -- NEU: Index für schnelle Cronjob-Suche
                INDEX `idx_email` (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',

            'mail_logs' => 'CREATE TABLE IF NOT EXISTS `mail_logs` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `timestamp` DATETIME NOT NULL,
                `recipient` VARCHAR(255) NOT NULL,
                `subject` VARCHAR(255) NOT NULL,
                `template` VARCHAR(100) NOT NULL,
                `status` TEXT,
                `data` JSON,
                INDEX `idx_recipient` (`recipient`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',

            'mail_queue' => 'CREATE TABLE IF NOT EXISTS `mail_queue` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `recipient` VARCHAR(255) NOT NULL,
                `subject` VARCHAR(255) NOT NULL,
                `template` VARCHAR(100) NOT NULL,
                `data` JSON NOT NULL,
                `attempts` INT DEFAULT 0,
                `created_at` DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',

            'magic_links' => 'CREATE TABLE IF NOT EXISTS `magic_links` (
                `token` VARCHAR(64) PRIMARY KEY,
                `email` VARCHAR(255) NOT NULL,
                `code` VARCHAR(10),
                `expires` DATETIME,
                INDEX `idx_code` (`code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',

            'pending_verifications' => 'CREATE TABLE IF NOT EXISTS `pending_verifications` (
                `token` VARCHAR(64) PRIMARY KEY,
                `expires` DATETIME,
                `data` JSON
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',

            'verified_pending' => 'CREATE TABLE IF NOT EXISTS `verified_pending` (
                `token` VARCHAR(64) PRIMARY KEY,
                `expires` DATETIME,
                `data` JSON
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',

            'vouchers_archive' => 'CREATE TABLE IF NOT EXISTS `vouchers_archive` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `code` VARCHAR(50),
                `redeemed_at` DATETIME,
                `user_name` VARCHAR(255),
                `user_plot` VARCHAR(10),
                INDEX `idx_code` (`code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',

            'login_attempts' => 'CREATE TABLE IF NOT EXISTS `login_attempts` (
                `ip_address` VARCHAR(45) PRIMARY KEY,
                `attempts` INT NOT NULL DEFAULT 1,
                `last_attempt` DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',

            'update_migrations' => 'CREATE TABLE IF NOT EXISTS `update_migrations` (
                `id` VARCHAR(50) PRIMARY KEY,
                `version` VARCHAR(50) NOT NULL,
                `executed_at` DATETIME NOT NULL,
                UNIQUE KEY `idx_version` (`version`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        ];
    }
}
