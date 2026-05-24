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

    // Bankangaben
    'bank_transfer_allowed' => true, // AUF TRUE SETZEN für die Nutzung von Überweisung
    'iban'                  => 'DE12 3456 7890 1234 5678 90',
    'bic'                   => 'GENODES1M00', // Wichtig für EPC-QR-Code
    'kontoinhaber'          => 'KGA e.V.',
    'usage_pattern'         => 'EFG-{{nachname}}-{{vorname}}-{{code}}', // Dynamisches Muster

    // --- PAYPAL (Optional) ---
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
];
