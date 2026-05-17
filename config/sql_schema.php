<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

/**
 * Definition der MySQL-Tabellenstruktur
 *
 * Path: config/sql_schema.php
 */

declare(strict_types=1);

return [
    'groups' => 'CREATE TABLE IF NOT EXISTS `groups` (
        `id` VARCHAR(50) PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `permissions` TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;',

    'users' => 'CREATE TABLE IF NOT EXISTS `users` (
        `id` VARCHAR(50) PRIMARY KEY,
        `username` VARCHAR(50) NOT NULL,
        `group` VARCHAR(50) NOT NULL,
        `pass` VARCHAR(255) NOT NULL,
        UNIQUE KEY `idx_username` (`username`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;',

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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;',

    'permits' => "CREATE TABLE IF NOT EXISTS `permits` (
        `code` varchar(50) NOT NULL,
        `templateKey` varchar(50) NOT NULL,
        `name` varchar(255) NOT NULL,
        `email` varchar(255) DEFAULT NULL,
        `kennzeichen` varchar(20) DEFAULT NULL,
        `parzelle` varchar(10) NOT NULL,
        `typ` varchar(20) NOT NULL,
        `firma` varchar(255) DEFAULT NULL,
        `zweck` varchar(255) NOT NULL,
        `preisSnapshot` decimal(10,2) NOT NULL,
        `von` date NOT NULL,
        `bis` date NOT NULL,
        `status` varchar(20) NOT NULL DEFAULT 'wartend',
        `isSuspended` tinyint(1) NOT NULL DEFAULT 0,
        `suspensionReason` text DEFAULT NULL,
        `erstellt` datetime NOT NULL,
        `internerKommentar` text DEFAULT NULL,
        PRIMARY KEY (`code`),
        KEY `idx_kennzeichen` (`kennzeichen`),
        KEY `idx_parzelle` (`parzelle`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    'mail_logs' => 'CREATE TABLE IF NOT EXISTS `mail_logs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `timestamp` DATETIME,
        `recipient` VARCHAR(255),
        `subject` VARCHAR(255),
        `template` VARCHAR(100),
        `status` TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;',

    'mail_queue' => 'CREATE TABLE IF NOT EXISTS `mail_queue` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `recipient` VARCHAR(255) NOT NULL,
        `subject` VARCHAR(255) NOT NULL,
        `template` VARCHAR(100) NOT NULL,
        `data` LONGTEXT NOT NULL, -- JSON serialisierte Daten
        `attempts` INT DEFAULT 0,
        `created_at` DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;',

    'magic_links' => 'CREATE TABLE IF NOT EXISTS `magic_links` (
        `token` VARCHAR(64) PRIMARY KEY,
        `email` VARCHAR(255),
        `code` VARCHAR(10),
        `expires` INT
    ) ENGINE=InnoDB;',

    'pending_verifications' => 'CREATE TABLE IF NOT EXISTS `pending_verifications` (
        `token` VARCHAR(64) PRIMARY KEY,
        `expires` INT,
        `data` LONGTEXT
    ) ENGINE=InnoDB;',

    'verified_pending' => 'CREATE TABLE IF NOT EXISTS `verified_pending` (
        `token` VARCHAR(64) PRIMARY KEY,
        `expires` INT,
        `data` LONGTEXT
    ) ENGINE=InnoDB;',

    'vouchers_archive' => 'CREATE TABLE IF NOT EXISTS `vouchers_archive` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `code` VARCHAR(50),
        `redeemed_at` DATETIME,
        `user_name` VARCHAR(255),
        `user_plot` VARCHAR(10)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;',
];
