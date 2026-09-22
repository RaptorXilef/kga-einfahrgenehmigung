<?php

/**
 * Core-Systemsteuerung (Infrastruktur & Umgebung)
 *
 * Diese Datei definiert die grundlegende Laufzeitumgebung des Servers.
 * Änderungen hier sollten nur durch den Systemadministrator vorgenommen werden.
 *
 * Path: config/config.php
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */

declare(strict_types=1);

return [
    'disable_backdoor' => false,     // KGA Sicherheitstoggle
    'disable_superadmin' => false,   // KGA Sicherheitstoggle
    'stealth_superadmins' => true,   // Wenn true, werden SuperAdmin Aktivitäten nicht ins AuditLog geschrieben

    // --- WARTUNGSMODUS (MAINTENANCE) ---
    // Feingranulare Steuerung für Ausfallzeiten und Updates.
    'maintenance' => [
        // Globale Schalter
        'frontend' => false, // true = Sperrt das gesamte öffentliche Frontend
        'admin' => false, // true = Sperrt das gesamte Admin-Dashboard
        'api' => false, // true = Sperrt alle API-Schnittstellen (Cronjobs, Webhooks, etc.)

        // Globale Standard-Nachricht
        'message' => "Wir aktualisieren gerade das System, um Ihnen den bestmöglichen Service zu bieten.\nIn Kürze sind wir wieder für Sie da.",

        // Granulare Steuerung einzelner Seiten (Pfad => true | "Eigener Grund")
        'routes' => [
            // --- FRONTEND ROUTEN ---
            // '/'                        => 'Das Antragsformular ist kurzzeitig offline.',
            // '/check'                   => true,
            // '/checkout'                => 'Das Bezahlsystem ist wegen eines Bank-Updates kurzzeitig offline.',
            // '/history'                 => true,
            // '/history_login'           => true,
            // '/history_request_link'    => true,
            // '/history_submit_code'     => true,
            // '/history_verify_token'    => true,
            // '/history_cancel_permit'   => true,
            // '/history_print'           => true,
            // '/history_logout'          => true,
            // '/success'                 => true,
            // '/verify'                  => true,
            // '/datenschutz'             => true,
            // '/impressum'               => true,
            // '/admin_login'             => true,

            // --- ADMIN ROUTEN ---
            // '/admin'                   => 'Das Dashboard wird gewartet.',
            // '/profile'                 => true,
            // '/users'                   => true,
            // '/changelog'               => true,
            // '/admin_logout'            => true,
            // '/admin_print'             => true,

            // --- API & CRONJOB ROUTEN ---
            // '/api/cron/archive'        => 'Archivierung pausiert.',
            // '/api/cron/backup'         => 'Backups pausiert.',
            // '/api/cron/reminders'      => 'Mahnwesen pausiert.',
            // '/api/cron/spam_sync'      => 'Spam-Sync pausiert.',
            // '/api/process_mail_queue'  => 'Mail-Queue pausiert.',
            // '/api/system_update'       => 'System-Update API gesperrt.',
            // '/api/get_template_price'  => 'Preisberechnung offline.',
            // '/api/get_date_info'       => 'Datumsservice offline.',
            // '/api/capture'             => 'Zahlungs-Capture blockiert.',
            // '/api/create_order'        => 'Bestell-Schnittstelle blockiert.',
            // '/api/finalize_wire'       => true,
            // '/api/ping'                => true,
            // '/api/mark_changelog_read' => true,
            // '/api/search_permits'      => true,
        ],
    ],

    // --- UMGEBUNGSSTEUERUNG ---
    'test_mode' => false, // true = Sandbox-Modus (PayPal & Mails umgeleitet) | false = Produktion
    'debug_mode' => false, // true = Zeigt PHP-Fehler im Klartext und deaktiviert den Routen-Cache, Mails landen im Ordner `storage/debug_mails/` statt versandt zu werden
];
