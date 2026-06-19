<?php

/**
 * Vereinsdaten
 *
 * Path: config/organization.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */

declare(strict_types=1);

return [
    // Basis URL der Webseite des Tools
    // Die Webadresse des Servers, auf dem Das Tool installiert ist
    'base_url' => 'https://deine-kga-domain-fuer-kga-zufahrts-manager.de/', // Pflichtfeld!

    // Angaben zum KGA-Verein
    'vereins_name'       => 'KGA e.V.',
    'prefix'             => 'ZM', // Präfix für den Code (z.B. ZM-26-0020-X8Y1)
    'external_home_url'  => 'https://deine-kga-homepage.de', // Für den "Zurück"-Button
    'terminkalender_url' => 'https://deine-kga.de/termine',  // Sprechzeiten

    // --- Genehmigungs-Code ---
    /**
     * Legt fest, in welchem Format der Genehmigungscode vorliegt
     * Option 1 - Langer Code: [Buchstabenfolge, aktuell ML]-[Parzellennummern]-[Kennzeichen]-[Eindeutige ID]
     * Option 2 - Kurzer Code: [Eindeutige ID]
     * true = Langer Code
     * false = Kurzer Code (Standard)
     */
    'use_long_permit_code' => false, // true = ML-Parzelle-KZ-ID | false = Nur ID
];
