<?php

/**
 * Zeiten & Feiertage
 *
 * Path: config/times.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */

declare(strict_types=1);

return [
    /**
     * Erlaubte Einfahrzeiten pro Wochentag.
     * Alles außerhalb dieser Zeiten gilt als "Ruhezeit".
     * Format: 'tag' => [['Start', 'Ende'], ['Start', 'Ende']]
     */
    'opening_hours' => [
        'mon' => [['08:00', '13:00'], ['15:00', '20:00']],
        'tue' => [['08:00', '13:00'], ['15:00', '20:00']],
        'wed' => [['08:00', '13:00'], ['15:00', '20:00']],
        'thu' => [['08:00', '13:00'], ['15:00', '20:00']],
        'fri' => [['08:00', '13:00'], ['15:00', '20:00']],
        'sat' => [['08:00', '13:00'], ['15:00', '20:00']],
        'sun' => [], // Sonntag keine Einfahrt
    ],

    /**
     * Automatischer Berlin-Feiertags-Check
     * Berechnet: Neujahr, Frauentag (8.3.), Karfreitag, Ostermontag, Tag der Arbeit (1.5.),
     * Christi Himmelfahrt, Pfingstmontag, Tag der Dt. Einheit (3.10.), 1. & 2. Weihnachtsfeiertag
     */
    'use_auto_holidays' => true,

    /**
     * Automatischer Check für Sonntage/Feiertage
     * Mögliche Angaben:
     * 'Baden-Württemberg',
     * 'Bayern',
     * 'Berlin',
     * 'Brandenburg',
     * 'Bremen',
     * 'Hamburg',
     * 'Hessen',
     * 'Mecklenburg-Vorpommern',
     * 'Niedersachsen',
     * 'Nordrhein-Westfalen',
     * 'Rheinland-Pfalz',
     * 'Saarland',
     * 'Sachsen-Anhalt',
     * 'Sachsen',
     * 'Schleswig-Holstein',
     * 'Thüringen',
     */
    'holiday_check' => 'Berlin',

    // Eigene, feste Feier- oder Ruhetage, die UNABHÄNGIG von der Automatik immer gesperrt sind.
    // Format: YYYY-MM-DD
    // Beispiel: '2026-12-24', <--- Dieses Format arbeitet am schnellsten!
    // Alternative Formate: '2026.12.24', '24-12-2026', '24.12.2026',
    'custom_holidays' => [
        '2026-12-24',
    ],

    /**
     * Angezeigte Einfahrtszeiten bei gleichen Zeiten an verschiedenen Tagen
     * nicht gruppieren, sondern einzeln anzeigen.
     * false = Gruppiert (Mo, Di, Mi...)
     * true = Einzeln (Mo: ..., Di: ...)
     */
    'holiday_service_use_full_list' => false,
];
