<?php

/**
 * Speicher-Routen und Backup-Infrastruktur
 *
 * Regelt die physische Zuordnung der Datenbereiche zu den Speicher-Engines
 * sowie die automatisierten Rotations-Zyklen der Datensicherungen.
 *
 * Path: config/storage.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */

declare(strict_types=1);

return [
    // --- RELATIONALES BACKEND (MYSQL) ---
    'database' => [
        'enabled' => false,
        'host'    => 'localhost',
        'port'    => '', // Optionaler Port
        'dbname'  => 'kga_einfahrts_manager',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],
];
