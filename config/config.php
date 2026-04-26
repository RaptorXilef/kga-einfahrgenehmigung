<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * Beispiel-Konfiguration für das Ausnahmegenehmigungs-System (v0.4.0).
 *
 * @file      config/config.php.example
 *
 * @since     0.4.0
 */

declare(strict_types=1);

return [
    // --- VEREIN & BASIC ---
    'vereins_name' => 'KGA e.V.',
    'prefix'       => 'ML', // Präfix für den Code (z.B. ML-26-0020-X8Y1)
    'base_url'     => 'https://deine-domain.de/',
    'geheimnis'    => 'DEIN_SUPER_GEHEIMES_PASSWORT_HIER',
    'test_mode'    => true,

    // --- PREISE & ZAHLUNG ---
    'prices' => [
        'pkw' => 3.00,
        'lkw' => 10.00,
    ],
    'iban'                  => 'DE12 3456 7890 1234 5678 90',
    'kontoinhaber'          => 'KGA e.V.',
    'payment_due_days'      => 14, // Zahlungsziel in Tagen
    'paypal_enabled'        => false, // Standardmäßig deaktiviert
    'bank_transfer_allowed' => true,

    // PayPal API (Optional)
    'paypal_client_id' => 'DEINE_CLIENT_ID',
    'paypal_secret'    => 'DEIN_SECRET',

    // --- E-MAIL & DATENSPEICHER
    'vorstand_email' => 'vorstand@deine-kga.de',
    'storage_path'   => 'storage/daten.json',

    // SMTP Einstellungen
    'mail' => [
        'host'             => 'smtp.dein-provider.de',
        'port'             => 465,
        'user'             => 'no-reply@deine-kga.de',
        'pass'             => 'dein-passwort',
        'from'             => 'no-reply@deine-kga.de',
        'test_mail_active' => false, // Auch im Testmodus Mails senden?
    ],

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

    // --- ADMIN & SICHERHEIT ---
    'admin_users' => [
        'admin' => [
            'pass'  => '$2y$10$xyz...', // Password-Hash
            'level' => 1, // Vollzugriff
        ],
        'vorstand' => [
            'pass'  => '$2y$10$abc...',
            'level' => 2, // Nur Einsicht
        ],
    ],

    // --- DESIGN ---
    'jahresFarbe'     => '#2ecc71', // Die Farbe für die gültige PDF/Mail
    'vorlaeufigFarbe' => '#f1c40f', // Gelb für "Wartend" (Verwaltungsintern)
];
