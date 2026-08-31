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
    'use_pseudo_cron' => true, // Wird in Phase 2 entfernt
    'archive_grace_days' => 0,

    'backup_settings' => [
        'enabled' => true,
        'interval_hours' => 24, // Wird in Phase 2 entfernt
        'max_backups' => 15,
        'sub_folder' => 'backups',
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
