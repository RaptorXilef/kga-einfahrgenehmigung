<?php

declare(strict_types=1);

/**
 * Speicher-Routen und Backup-Infrastruktur
 *
 * Regelt die physische Zuordnung der Datenbereiche zu den Speicher-Engines
 * sowie die automatisierten Rotations-Zyklen der Datensicherungen.
 * MySQL ist die exklusive Single Source of Truth.
 *
 * Path: config/storage.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */

return [
    'storage_path_prefix' => 'storage/',
    'archive_grace_days' => 0,

    'backup_settings' => [
        'enabled' => true,
        'max_backups' => 15,
        'sub_folder' => 'backups',
        'zip_password' => '', // Optional: AES-256 Passwort für das ZIP-Archiv
        'ftp' => [
            'enabled' => false, // Auf true setzen für Offsite-Backups
            'host' => 'ftp.dein-backup-server.de',
            'port' => 21,
            'user' => 'backup_user',
            'pass' => 'geheim123',
            'path' => '/kga_backups/', // Zielordner auf dem FTP
            'ssl' => true, // Empfohlen (FTPS)
        ],
    ],

    'storage_config' => [
        'permits' => ['table' => 'permits'],
        'permits_archive' => ['table' => 'permits_archive'],
        'permits_cancelled' => ['table' => 'permits_cancelled'],
        'users' => ['table' => 'users'],
        'roles' => ['table' => 'roles'],
        'vouchers' => ['table' => 'vouchers'],
        'vouchers_archive' => ['table' => 'vouchers_archive'],
        'mail_log' => ['table' => 'mail_logs'],
        'mail_queue' => ['table' => 'mail_queue'],
        'magic_links' => ['table' => 'magic_links'],
        'pending_verification' => ['table' => 'pending_verifications'],
        'verified_pending' => ['table' => 'verified_pending'],
        'login_attempts' => ['table' => 'login_attempts'],
        'update_migrations' => ['table' => 'update_migrations'],
        'audit_logs' => ['table' => 'audit_logs'],
    ],

    // --- RELATIONALES BACKEND (MYSQL) ---
    'database' => [
        'enabled' => true,
        'host' => 'localhost',
        'port' => '', // Optionaler Port
        'dbname' => 'kga_einfahrts_manager',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
];
