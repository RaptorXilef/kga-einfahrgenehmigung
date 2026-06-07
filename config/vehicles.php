<?php

/**
 * Fahrzeug-Konfiguration
 *
 * Eingetragene Fahrzeuge niemals löschen, nur deaktivieren, sonst wird Datenbank Fehler riskiert!
 *
 * Path: config/vehicles.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */

declare(strict_types=1);

return [
    // --- FAHRZEUG-KONFIGURATION ---
    'vehicle_types' => [
        'pkw' => [
            'label'        => 'Privat PKW',
            'icon'         => 'assets/img/icons/icon-automobile.webp', // Pfad ab public/
            'show_company' => false, // Zeigt das Firmenfeld NICHT
            'active'       => true, // Sichtbar für neue Buchungen
        ],
        'lkw' => [
            'label'        => 'Lieferant / Firma / LKW',
            'icon'         => 'assets/img/icons/icon-delivery-truck.webp',
            'show_company' => true,  // Zeigt das Firmenfeld
            'active'       => true,
        ],
        'entsorg' => [
            'label'        => 'Abwasser / Entsorgung',
            'icon'         => 'assets/img/icons/icon-biohazard.webp',
            'show_company' => true,
            'active'       => false, // ARCHIVIERT: Erscheint nicht mehr im Dropdown!
        ],
    ],
];
