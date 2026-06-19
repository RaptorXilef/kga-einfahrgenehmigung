<?php

/**
 * E-Mail Server Daten um Versandt (Post-Ausgangsserver)
 *
 * Path: config/email.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */

declare(strict_types=1);

return [
    // --- E-MAIL (Zwei Welten System) ---
    'mail' => [
        'host'                    => 'smtp.dein-provider.de',
        'port'                    => 465,
        'user'                    => 'no-reply@deine-kga.de',
        'pass'                    => 'dein-passwort',
        'from'                    => 'no-reply@deine-kga.de',
        'test_mail_active'        => false,
        'send_board_notification' => true, // Aktiviert (true) deaktiviert (false) die Zusammenfassungen an den Vorstand
        'recipients'              => [
            'live' => 'vorstand@echte-domain.de', // Hier landen alle Vorstand-Mails im Live-Modus
            'test' => 'deine-private-mail@test.de', // Hier landen alle Vorstand-Mails im Testmodus
        ],
    ],

    // --- MAIL LOG EINSTELLUNGEN ---
    'mail_log_max_entries'   => 5000, // Maximale Anzahl in der JSON-Datei
    'mail_log_display_limit' => 250,  // Anzahl der gezeigten Einträge im Admin-Tab

    // --- TIMEOUTS (in Stunden/Minuten) ---
    'magic_link_duration'    => 15, // Minuten, die ein Login-Link aus der E-Mail gültig ist
    'hours_pending_verify'   => 24, // Zeit für E-Mail Bestätigung in Stunden
    'hours_pending_finalize' => 48, // Zeit für Bezahlung oder Überweisungsantrag nach Verifizierung
];
