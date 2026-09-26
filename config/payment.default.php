<?php

declare(strict_types=1);

return [
    'bank_transfer_allowed' => true,
    'iban' => 'DE12 3456 7890 1234 5678 90',
    'bic' => 'GENODES1M00',
    'kontoinhaber' => 'KGA e.V.',
    'usage_pattern' => 'EFG-{{code}}-{{nachname}}-{{vorname}}',

    /**
     * TEMPORÄRER FIX (Für 3 Monate):
     * Wenn true, werden 8-stellige Genehmigungscodes beim CSV-Bankabgleich auch dann erkannt,
     * wenn im Verwendungszweck nur die letzten 6 Stellen des Codes angegeben wurden.
     * Zum Deaktivieren einfach auf false stellen.
     */
    'allow_legacy_6char_suffix_match' => true,

    /**
     * Genehmigung erst gültig wenn bezahlt oder sofort?
     * true = Erst gültig wenn bezahlt
     * false = Sofort gültig, auch wenn noch nicht bezahlt
     */
    'require_payment_for_validity' => true,
    'payment_due_days_before_validity' => 1,
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
    // Mindest-Abstand in Tagen, bevor ein Pächter erneut gemahnt werden darf
    'payment_reminder_cooldown_days' => 7,

    // --- PAYPAL (Zwei Welten System) (Optional, wenn Paypal genutzt wird) ---
    'paypal' => [
        'enabled' => false, // AUF TRUE SETZEN für die Nutzung von Paypal
        'sandbox' => [
            'client_id' => 'SANDBOX_ID_HIER', // Hier ID aus dem PayPal Developer Portal
            'secret' => 'SANDBOX_SECRET_HIER', // Hier Secret aus dem PayPal Developer Portal
        ],
        'live' => [
            'client_id' => 'LIVE_ID_HIER', // Hier ID aus dem PayPal Business Portal
            'secret' => 'LIVE_SECRET_HIER', // Hier Secret aus dem PayPal Business Portal
        ],
    ],
];
