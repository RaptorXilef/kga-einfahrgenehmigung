<?php

// Pfad: config\colors.php

declare(strict_types=1);

/**
 * Zeiten & Feiertage
 */

return [
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
        'sat' => [['08:00', '13:00'], ['15:00', '20:00']],
        'sun' => [], // Sonntag keine Einfahrt
    ],

    // Automatischer Check für Sonntage/Feiertage
    'holiday_check' => 'Berlin',
];
