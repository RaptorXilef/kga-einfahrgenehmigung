<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

/**
 * Beispiel-Konfiguration für das Ausnahmegenehmigungs-System.
 *
 * Path:      config/config.php
 */

declare(strict_types=1);

return [
    // --- BASIC ---
    'vereins_name'       => 'KGA e.V.',
    'prefix'             => 'ML', // Präfix für den Code (z.B. ML-26-0020-X8Y1)
    'external_home_url'  => 'https://deine-kga-homepage.de', // Für den "Zurück"-Button
    'terminkalender_url' => 'https://deine-kga.de/termine', // Sprechzeiten
    // fungiert als "Salt" für die Sicherheit des token in der Zugriffs-URL die per E-Mail versandt wird
    'geheimnis' => 'DEIN_SUPER_GEHEIMES_PASSWORT_HIER',

    // --- DESIGN ---
    'jahresFarbe'     => '#2ecc71', // Die Farbe für die gültige PDF/Mail
    'vorlaeufigFarbe' => '#f1c40f', // Gelb für "Wartend" (Verwaltungsintern)
    // Design für Dauerkarten
    'permanent_color' => '#3498db', // Blau statt Grün

    // --- AUSWAHLMENÜS ---
    'purposes' => [
        'bau'     => 'Baumaßnahmen (genehmigt)',
        'abriss'  => 'Abriss',
        'liefer'  => 'Lieferung',
        'entsorg' => 'Entsorgung/Abfuhr',
    ],

    // --- FAHRZEUG-KONFIGURATION ---
    'vehicle_types' => [
        'pkw' => [
            'label'        => 'Privat PKW',
            'icon'         => 'assets/img/icons/icon-automobile.webp', // Pfad ab public/
            'show_company' => false, // Zeigt das Firmenfeld NICHT
        ],
        'lkw' => [
            'label'        => 'LKW / Lieferant / Firma',
            'icon'         => 'assets/img/icons/icon-delivery-truck.webp',
            'show_company' => true,  // Zeigt das Firmenfeld
        ],
        'entsorg' => [
            'label'        => 'Abwasser / Entsorgung',
            'icon'         => 'assets/img/icons/icon-biohazard.webp',
            'show_company' => true,
        ],
    ],

    // --- LOGIK & ZEITRÄUME ---
    /**
     * Erlaubte Einfahrzeiten pro Wochentag.
     * Alles außerhalb dieser Zeiten gilt als "Ruhezeit".
     * Format: 'tag' => [['Start', 'Ende'], ['Start', 'Ende']]
     */
    'opening_hours' => [
        'mon' => [['08:00', '12:00'], ['15:00', '20:00']],
        'tue' => [['08:00', '12:00'], ['15:00', '20:00']],
        'wed' => [['08:00', '12:00'], ['15:00', '20:00']],
        'thu' => [['08:00', '12:00'], ['15:00', '20:00']],
        'fri' => [['08:00', '12:00'], ['15:00', '20:00']],
        'sat' => [['08:00', '13:00'], ['15:00', '20:00']], // Samstag Mittagspause früher
        'sun' => [], // Sonntag generell keine Einfahrt erlaubt
    ],

    // Automatischer Check für Sonntage/Feiertage
    'holiday_check' => 'Berlin',

    // --- TEMPLATES FÜR GENEHMIGUNGEN ---
    'permit_templates' => [
        'std_7' => [
            'type'   => 'standard',
            'label'  => 'Ausnahmegenehmigung 7 Tage',
            'days'   => 7,
            'prices' => ['pkw' => 3.00, 'lkw' => 10.00],
            'public' => true, // Im öffentlichen Formular sichtbar
        ],
        'std_14' => [
            'type'   => 'standard',
            'label'  => 'Ausnahmegenehmigung 14 Tage',
            'days'   => 14,
            'prices' => ['pkw' => 5.00, 'lkw' => 15.00],
            'public' => false, // Nur Admin oder via Gutschein
        ],
        'std_30' => [
            'type'   => 'standard',
            'label'  => 'Ausnahmegenehmigung 1 Monat',
            'days'   => 30,
            'prices' => ['pkw' => 10.00, 'lkw' => 25.00],
            'public' => false,
        ],
        'perm_3' => [
            'type'   => 'permanent',
            'label'  => 'Dauereinfahrgenehmigung (1 Quartal)',
            'days'   => 90,
            'prices' => ['pkw' => 20.00, 'lkw' => 50.00],
            'public' => false,
        ],
        'perm_6' => [
            'type'   => 'permanent',
            'label'  => 'Dauereinfahrgenehmigung (2 Quartale)',
            'days'   => 180,
            'prices' => ['pkw' => 35.00, 'lkw' => 80.00],
            'public' => false,
        ],
        'perm_12' => [
            'type'   => 'permanent',
            'label'  => 'Dauereinfahrgenehmigung (Gesamtjahr)',
            'days'   => 365,
            'prices' => ['pkw' => 60.00, 'lkw' => 150.00],
            'public' => false,
        ],
        'custom_std' => [
            'type'   => 'standard',
            'label'  => 'Spezialzeitraum (Standard)',
            'days'   => 'custom',
            'prices' => ['pkw' => 0.00, 'lkw' => 0.00], // Manuelle Preisabsprache
            'public' => false,
        ],
        'custom_perm' => [
            'type'   => 'permanent',
            'label'  => 'Spezialzeitraum (Dauereinfahrt)',
            'days'   => 'custom',
            'prices' => ['pkw' => 0.00, 'lkw' => 0.00],
            'public' => false,
        ],
    ],

    /**
     * Zahlungsziel für Überfälligkeit
     * Ist der Zeitraum, den der Nutezr hat, um die Überweisung zu tätigen (Steht in E-Mail als Stichtag mit Datum)
     */
    'payment_due_days' => 14,
    /**
     * Nach überschreitung dieser Zeit (payment_due_days +2 Tage)
     * werden die Buchhalter im System über die Überfälligkeit informeirt.
     */
    'payment_due_days_notify' => 2,

    // Bankangaben noch aufbauen wie Paypal (Überpunkt ueberweisung oder bank oder ähnliches)
    'bank_transfer_allowed' => true, // AUF TRUE SETZEN für die Nutzung von Überweisung
    'iban'                  => 'DE12 3456 7890 1234 5678 90',
    'bic'                   => 'GENODES1M00', // Wichtig für EPC-QR-Code
    'kontoinhaber'          => 'KGA e.V.',
    'usage_pattern'         => 'EFG-{{nachname}}-{{vorname}}-{{code}}', // Dynamisches Muster

    // --- PAYPAL (Zwei Welten System) (Optional, wenn Paypal genutzt wird) ---
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
        'host'       => 'smtp.dein-provider.de',
        'port'       => 465,
        'user'       => 'no-reply@deine-kga.de',
        'pass'       => 'dein-passwort',
        'from'       => 'no-reply@deine-kga.de',
        'recipients' => [
            'live' => 'vorstand@echte-domain.de', // Hier landen alle Vorstand-Mails im Live-Modus
            'test' => 'deine-private-mail@test.de', // Hier landen alle Vorstand-Mails im Testmodus
        ],
    ],
    // --- MAIL LOG EINSTELLUNGEN ---
    'mail_log_max_entries'   => 5000, // Maximale Anzahl in der JSON-Datei
    'mail_log_display_limit' => 50,  // Anzahl der gezeigten Einträge im Admin-Tab

    // --- TIMEOUTS (in Stunden) ---
    'hours_pending_verify'   => 24, // Zeit für E-Mail Bestätigung
    'hours_pending_finalize' => 48, // Zeit für Bezahlung oder Überweisungsantrag nach Verifizierung
    'magic_link_duration'    => 15, // Minuten, die ein Login-Link gültig ist

    // --- AUTH & USER MANAGEMENT ---
    'superadmin' => [
        'user' => 'superadmin',
        'pass' => 'passwort1234', // In Produktion sofort ändern!
    ],
    'roles' => [
        0 => 'Superadmin',
        1 => 'Vorstand/Admin',
        2 => 'Finanzen/Buchhaltung',
        3 => 'Aufsicht/User',
    ],

    // --- Speicher-Konfiguration ---
    'storage_config' => [
        // Haupt-Datenbank für fertige Genehmigungen (ehemals daten.json)
        'permits' => [
            'type'  => 'json', // 'json' oder 'mysql'
            'table' => 'permits',
            'file'  => 'permits_active.json',
        ],
        // Archiv für abgelaufene Genehmigungen aus vergangenen Jahren
        'permits_archive' => [
            'type'         => 'json', // Auch hier: 'json' oder 'mysql'
            'table'        => 'permits_archive', // In SQL eine (1) Tabelle für alle alten Jahre
            'file_pattern' => 'permits_archive_{YEAR}.json', // Pattern für die Dateinamen
        ],
        // Benutzerkonten für den Admin-Bereich
        'users' => [
            'type'  => 'json',
            'table' => 'users',
            'file'  => 'users.json',
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
    ],

    // Pfad zum Storage-Ordner (relativ zum Root)
    'storage_path_prefix' => 'storage/',

    // MySQL Zugangsdaten
    'database' => [
        'host'    => 'localhost',
        'dbname'  => 'kga_einfahrt',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    // --- UMGEBUNGSSTEUERUNG ---

    /**
     * GLOBALER TEST-MODUS
     * true  => PayPal Sandbox & Kein echter Mailversand (wenn test_mail_active = false)
     * false => PayPal LIVE & Echter Mailversand
     */
    'test_mode' => false,  // TRUE = Sandbox & Test-Mails | FALSE = Live & Echt-Mails
    /**
     * ADMIN DEV MODE
     * true  => Überspringt den Login in /admin.php (Vollzugriff für Entwicklung)
     * false => Login zwingend erforderlich
     */
    'admin_dev_mode' => false,  // TRUE = Kein Admin-Login nötig

    // Wenn true, werden alle öffentlichen Seiten umgeleitet
    'maintenance_mode'       => false, // Pächter-Seiten sperren
    'maintenance_mode_admin' => false, // AUCH Admin-Seiten sperren
];
