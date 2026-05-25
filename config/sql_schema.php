<?php

/**
 * Definition der MySQL-Tabellenstruktur
 *
 * Path: config/sql_schema.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */

declare(strict_types=1);

return [
    'groups' => 'CREATE TABLE IF NOT EXISTS `groups` (
        `id` VARCHAR(50) PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `permissions` TEXT
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
        `multi_use` TINYINT(1),
        `max_uses` INT,
        `uses_count` INT DEFAULT 0,
        `expires_at` DATETIME NULL,
        `date_mode` VARCHAR(20),
        `created_by` VARCHAR(50),
        `created_at` DATETIME,
        `data` TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',

    'permits' => 'CREATE TABLE IF NOT EXISTS `permits` (
        `code` VARCHAR(50) NOT NULL,
        `templateKey` VARCHAR(50) NOT NULL,
        `name` VARCHAR(255) NOT NULL,
        `email` VARCHAR(255) DEFAULT NULL,
        `kennzeichen` VARCHAR(20) DEFAULT NULL,
        `parzelle` VARCHAR(10) NOT NULL,
        `typ` VARCHAR(20) NOT NULL,
        `firma` VARCHAR(255) DEFAULT NULL,
        `zweck` VARCHAR(255) NOT NULL,
        `preisSnapshot` DECIMAL(10,2) NOT NULL,
        `von` DATE NOT NULL,
        `bis` DATE NOT NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT \'offen\',
        `isSuspended` TINYINT(1) NOT NULL DEFAULT 0,
        `suspensionReason` TEXT DEFAULT NULL,
        `erstellt` DATETIME NOT NULL,
        `internerKommentar` TEXT DEFAULT NULL,
        PRIMARY KEY (`code`),
        INDEX `idx_kennzeichen` (`kennzeichen`),
        INDEX `idx_parzelle` (`parzelle`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',

    'permits_archive' => 'CREATE TABLE IF NOT EXISTS `permits_archive` (
        `code` VARCHAR(50) NOT NULL,
        `templateKey` VARCHAR(50) NOT NULL,
        `name` VARCHAR(255) NOT NULL,
        `email` VARCHAR(255) DEFAULT NULL,
        `kennzeichen` VARCHAR(20) DEFAULT NULL,
        `parzelle` VARCHAR(10) NOT NULL,
        `typ` VARCHAR(20) NOT NULL,
        `firma` VARCHAR(255) DEFAULT NULL,
        `zweck` VARCHAR(255) NOT NULL,
        `preisSnapshot` DECIMAL(10,2) NOT NULL,
        `von` DATE NOT NULL,
        `bis` DATE NOT NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT \'offen\',
        `erstellt` DATETIME NOT NULL,
        `internerKommentar` TEXT DEFAULT NULL,
        PRIMARY KEY (`code`),
        INDEX `idx_kennzeichen` (`kennzeichen`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',

    'mail_logs' => 'CREATE TABLE IF NOT EXISTS `mail_logs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `timestamp` DATETIME NOT NULL,
        `recipient` VARCHAR(255) NOT NULL,
        `subject` VARCHAR(255) NOT NULL,
        `template` VARCHAR(100) NOT NULL,
        `status` TEXT,
        `data` LONGTEXT,
        INDEX `idx_recipient` (`recipient`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',

    'mail_queue' => 'CREATE TABLE IF NOT EXISTS `mail_queue` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `recipient` VARCHAR(255) NOT NULL,
        `subject` VARCHAR(255) NOT NULL,
        `template` VARCHAR(100) NOT NULL,
        `data` LONGTEXT NOT NULL,
        `attempts` INT DEFAULT 0,
        `created_at` DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',

    'magic_links' => 'CREATE TABLE IF NOT EXISTS `magic_links` (
        `token` VARCHAR(64) PRIMARY KEY,
        `email` VARCHAR(255) NOT NULL,
        `code` VARCHAR(10),
        `expires` INT,
        INDEX `idx_code` (`code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',

    'pending_verifications' => 'CREATE TABLE IF NOT EXISTS `pending_verifications` (
        `token` VARCHAR(64) PRIMARY KEY,
        `expires` INT,
        `data` LONGTEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',

    'verified_pending' => 'CREATE TABLE IF NOT EXISTS `verified_pending` (
        `token` VARCHAR(64) PRIMARY KEY,
        `expires` INT,
        `data` LONGTEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',

    'vouchers_archive' => 'CREATE TABLE IF NOT EXISTS `vouchers_archive` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `code` VARCHAR(50),
        `redeemed_at` DATETIME,
        `user_name` VARCHAR(255),
        `user_plot` VARCHAR(10),
        INDEX `idx_code` (`code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
];
