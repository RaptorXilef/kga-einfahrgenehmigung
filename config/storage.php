<?php

/**
 * Speicher- und Backup-Einstellungen
 *
 * Path: config/storage.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */

declare(strict_types=1);

return [
    // MySQL Zugangsdaten (Optional)
    'database' => [
        'enabled' => false,
        'host'    => 'localhost',
        'port'    => '', // Optionaler Port
        'dbname'  => 'kga_zufahrts_manager',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    // WICHTIG: Aktuell bitte bei json bleiben, da die Migration von MySQl zurück zu json noch nicht fertig ist!

    // Speicher-Strategie pro Bereich
    'storage_config' => [
        // Haupt-Datenbank für fertige Genehmigungen (ehemals daten.json)
        'permits' => [
            'type'  => 'json', // 'json' oder 'mysql'
            'table' => 'permits',
            'file'  => 'permits.json',
        ],
        // Archiv für abgelaufene Genehmigungen aus vergangenen Jahren
        'permits_archive' => [
            'type'  => 'json', // Auch hier: 'json' oder 'mysql'
            'table' => 'permits_archive', // In SQL eine (1) Tabelle für alle alten Jahre
            'file'  => 'permits_archive.json', // Pattern für die Dateinamen
        ],
        // Benutzerkonten für den Admin-Bereich
        'users' => [
            'type'  => 'json',
            'table' => 'users',
            'file'  => 'users.json',
        ],
        // Rechte-Gruppen für den Admin-Bereich
        'groups' => [
            'type'  => 'json',
            'table' => 'groups',
            'file'  => 'groups.json',
        ],
        // Aktive, noch nicht eingelöste Gutscheine
        'vouchers' => [
            'type'  => 'json',
            'table' => 'vouchers',
            'file'  => 'vouchers.json',
        ],
        // Historie der bereits genutzten Gutscheine
        'vouchers_archive' => [
            'type'  => 'json',
            'table' => 'vouchers_archive',
            'file'  => 'vouchers_archive.json',
        ],
        // Protokoll der versendeten E-Mails
        'mail_log' => [
            'type'  => 'json',
            'table' => 'mail_logs',
            'file'  => 'mail_log.json',
        ],
        // Temporärer Speicher für ausgehende E-Mails die auf SMTP Verbindung warten
        'mail_queue' => [
            'type'  => 'json',
            'table' => 'mail_queue',
            'file'  => 'mail_queue.json',
        ],
        // Temporäre Login-Codes für den Pächter-Verlauf
        'magic_links' => [
            'type'  => 'json',
            'table' => 'magic_links',
            'file'  => 'magic_links.json',
        ],
        // Warteraum 1: Antrag gestellt, E-Mail noch nicht bestätigt
        'pending_verification' => [
            'type'  => 'json',
            'table' => 'pending_verifications',
            'file'  => 'pending_verification.json',
        ],
        // Warteraum 2: E-Mail bestätigt, wartet auf Zahlung/Abschluss
        'verified_pending' => [
            'type'  => 'json',
            'table' => 'verified_pending',
            'file'  => 'verified_pending.json',
        ],
        // Ort, um die fehlerhafte Login-Versuche zu speichern
        'login_attempts' => [
            'type'  => 'json',
            'table' => 'login_attempts',
            'file'  => 'login_attempts.json',
        ],
    ],

    // Pfad zum Storage-Ordner (relativ zum Root)
    'storage_path_prefix' => 'storage/',

    // Automatisierung & Sicherung
    'backup_settings' => [
        'enabled'        => true,
        'interval_hours' => 24,       // Alle X Stunden ein Backup ziehen bei Admin-Aktivität
        'max_backups'    => 15,       // Wie viele Backup-Ordner behalten? (Rotation)
        'sub_folder'     => 'backups', // Ordnername innerhalb des storage-Pfads
    ],

    // -- CronJob - Automatisierte Archivierung ---
    'use_pseudo_cron'    => true, // Pseudo-Cron beim Admin-Login aktivieren
    'archive_grace_days' => 0,    // Tage - 0 = sofort nach Ablauf archivieren (wenn bezahlt)
];
