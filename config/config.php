<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

// Path:      config/config.php

declare(strict_types=1);

/**
 * Technische System-Konfiguration für das Ausnahmegenehmigungs-System.
 * Fachliche Einstellungen (Farben, Typen, Zeiten) liegen in separaten Dateien.
 */
return [
    // --- ZAHLUNGS-KONFIGURATION ---
    /**
     * Zahlungsziel für Überfälligkeit
     * Ist der Zeitraum, den der Nutezr hat, um die Überweisung zu tätigen (Steht in E-Mail als Stichtag mit Datum)
     */
    'payment_due_days' => 14, // Tage bis zur Überfälligkeit
    /**
     * Nach überschreitung dieser Zeit (payment_due_days +2 Tage)
     * werden die Buchhalter im System über die Überfälligkeit informeirt.
     */
    'payment_due_days_notify' => 2,  // Zusatztage bis die Verwaltung gewarnt wird

    // Bankangaben
    'bank_transfer_allowed' => true, // AUF TRUE SETZEN für die Nutzung von Überweisung
    'iban'                  => 'DE12 3456 7890 1234 5678 90',
    'bic'                   => 'GENODES1M00', // Wichtig für EPC-QR-Code
    'kontoinhaber'          => 'KGA e.V.',
    'usage_pattern'         => 'EFG-{{nachname}}-{{vorname}}-{{code}}', // Dynamisches Muster

    // --- PAYPAL (Zwei Welten System) ---
    'paypal' => [
        'enabled' => false, // AUF TRUE SETZEN für die Nutzung von Paypal
        'sandbox' => [
            'client_id' => 'SANDBOX_ID_HIER', // Hier ID aus dem PayPal Developer Portal
            'secret'    => 'SANDBOX_SECRET_HIER', // Hier Secret aus dem PayPal Developer Portal
        ],
        'live' => [
            'client_id' => 'LIVE_ID_HIER', // Hier ID aus dem PayPal Business Portal
            'secret'    => 'LIVE_SECRET_HIER', // Hier Secret aus dem PayPal Business Portal
        ],
    ],

    // --- E-MAIL (Zwei Welten System) ---
    'mail' => [
        'host'             => 'smtp.dein-provider.de',
        'port'             => 465,
        'user'             => 'no-reply@deine-kga.de',
        'pass'             => 'dein-passwort',
        'from'             => 'no-reply@deine-kga.de',
        'test_mail_active' => false,
        'recipients'       => [
            'live' => 'vorstand@echte-domain.de', // Hier landen alle Vorstand-Mails im Live-Modus
            'test' => 'deine-private-mail@test.de', // Hier landen alle Vorstand-Mails im Testmodus
        ],
    ],

    // --- MAIL LOG EINSTELLUNGEN ---
    'mail_log_max_entries'   => 5000, // Maximale Anzahl in der JSON-Datei
    'mail_log_display_limit' => 250,  // Anzahl der gezeigten Einträge im Admin-Tab

    // --- TIMEOUTS (in Stunden/Minuten) ---
    'hours_pending_verify'   => 24, // Zeit für E-Mail Bestätigung
    'hours_pending_finalize' => 48, // Zeit für Bezahlung oder Überweisungsantrag nach Verifizierung
    'magic_link_duration'    => 15, // Minuten, die ein Login-Link gültig ist

    // --- UMGEBUNGSSTEUERUNG ---
    /**
     * GLOBALER TEST-MODUS
     * true  => PayPal Sandbox & Kein echter Mailversand (wenn test_mail_active = false)
     * false => PayPal LIVE & Echter Mailversand
     */
    'test_mode' => false, // TRUE = Sandbox & Test-Mails | FALSE = Live & Echt-Mails

    /**
     * ADMIN DEV MODE
     * true  => Überspringt den Login in /admin.php (Vollzugriff für Entwicklung)
     * false => Login zwingend erforderlich
     */
    'admin_dev_mode' => false, // TRUE = Kein Admin-Login nötig

    // Wenn true, werden alle/bestimmte Seiten umgeleitet auf die maintenance.php
    'maintenance_mode'       => false, // Pächter-Seiten sperren
    'maintenance_mode_admin' => false, // AUCH Admin-Seiten sperren
];
