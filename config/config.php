<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * Beispiel-Konfiguration für das Ausnahmegenehmigungs-System (v0.4.0).
 *
 * @file      config/config.php.example
 *
 * @since     0.8.0
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

    // --- PREISE & ZAHLUNG ---
    'prices' => [
        'pkw' => 3.00,
        'lkw' => 10.00,
    ],
    // Bankangaben noch aufbauen wie Paypal (Überpunkt ueberweisung oder bank oder ähnliches)
    'bank_transfer_allowed' => true, // AUF TRUE SETZEN für die Nutzung von Überweisung
    'iban'                  => 'DE12 3456 7890 1234 5678 90',
    'bic'                   => 'GENODES1M00', // Wichtig für EPC-QR-Code
    'kontoinhaber'          => 'KGA e.V.',
    'payment_due_days'      => 14, // Zahlungsziel in Tagen
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

    // --- DESIGN ---
    'jahresFarbe'     => '#2ecc71', // Die Farbe für die gültige PDF/Mail
    'vorlaeufigFarbe' => '#f1c40f', // Gelb für "Wartend" (Verwaltungsintern)

    // --- LOGIK & ZEITRÄUME ---
    'permit_duration' => 7, // Standard-Zeitraum (1 Woche)
    'opening_hours'   => [
        'earliest' => '07:00',
        'latest'   => '20:00',
    ],
    'holiday_check' => 'Berlin', // Automatischer Check für Sonntage/Feiertage

    // --- AUSWAHLMENÜS ---
    'purposes' => [
        'bau'     => 'Baumaßnahmen (genehmigt)',
        'abriss'  => 'Abriss',
        'liefer'  => 'Lieferung',
        'entsorg' => 'Entsorgung/Abfuhr',
    ],
    'vehicle_types' => [
        'pkw' => 'Privat PKW',
        'lkw' => 'LKW / Lieferant / Firma',
    ],

    // --- UMGEBUNGSSTEUERUNG ---

    /**
     * GLOBALER TEST-MODUS
     * true  => PayPal Sandbox & Kein echter Mailversand (wenn test_mail_active = false)
     * false => PayPal LIVE & Echter Mailversand
     */
    'test_mode' => true,  // TRUE = Sandbox & Test-Mails | FALSE = Live & Echt-Mails
    /**
     * ADMIN DEV MODE
     * true  => Überspringt den Login in /admin.php (Vollzugriff für Entwicklung)
     * false => Login zwingend erforderlich
     */
    'admin_dev_mode' => true,  // TRUE = Kein Admin-Login nötig
];
