<?php

/**
 * Zeiten & Feiertage
 *
 * Path: config/times.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */

declare(strict_types=1);

return [
    /**
     * Standard-Einfahrzeiten (Default).
     * Diese Zeiten gelten IMMER, es sei denn, das aktuelle Datum fällt in eine
     * der unten definierten "seasons" (Saison-Zeiten).
     */
    'default_opening_hours' => [
        'mon' => [['07:00', '13:00'], ['15:00', '20:00']],
        'tue' => [['07:00', '13:00'], ['15:00', '20:00']],
        'wed' => [['07:00', '13:00'], ['15:00', '20:00']],
        'thu' => [['07:00', '13:00'], ['15:00', '20:00']],
        'fri' => [['07:00', '13:00'], ['15:00', '20:00']],
        'sat' => [['07:00', '13:00'], ['15:00', '20:00']],
        'sun' => [], // Sonntag keine Einfahrt
    ],

    /**
     * Saison-abhängige Einfahrzeiten (Optional).
     * Format für start / end: 'MM-DD' (Monat-Tag).
     * Überschreiben die 'default_opening_hours', wenn das Datum reinpasst.
     */
    'seasons' => [
        // Beispiel 1: Erweiterte Zeiten im Sommer (Mai bis Ende August)
        [
            'start'         => '10-01',
            'end'           => '03-31',
            'opening_hours' => [
                'mon' => [['07:00', '20:00']],
                'tue' => [['07:00', '20:00']],
                'wed' => [['07:00', '20:00']],
                'thu' => [['07:00', '20:00']],
                'fri' => [['07:00', '20:00']], // <-- Freitags früher offen
                'sat' => [['07:00', '20:00']], // <-- Samstags früher offen
                'sun' => [],
            ],
        ],
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
