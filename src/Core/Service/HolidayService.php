<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

declare(strict_types=1);

namespace App\Core\Service;

/**
 * Service zur Prüfung von Berliner Feiertagen und Sperrzeiten.
 */
final readonly class HolidayService
{
    /**
     * Prüft, ob ein Datum ein Sonntag oder ein Berliner Feiertag ist.
     */
    public function isRestrictedDay(\DateTimeImmutable $date): bool
    {
        // 1. Sonntage (0 = Sonntag)
        if ($date->format('w') === '0') {
            return true;
        }

        $year     = (int) $date->format('Y');
        $holidays = $this->getBerlinHolidays($year);

        return \in_array($date->format('Y-m-d'), $holidays, true);
    }

    /**
     * Berechnet alle gesetzlichen Feiertage für Berlin.
     */
    private function getBerlinHolidays(int $year): array
    {
        $easter = \DateTimeImmutable::createFromFormat('U', (string) \easter_date($year));

        return [
            $year . '-01-01', // Neujahr
            $year . '-03-08', // Frauentag (Berlin Spezial)
            $year . '-05-01', // Tag der Arbeit
            $year . '-10-03', // Tag der Deutschen Einheit
            $year . '-12-25', // 1. Weihnachtstag
            $year . '-12-26', // 2. Weihnachtstag
            // Bewegliche Feiertage via Ostern:
            $easter->modify('-2 days')->format('Y-m-d'),  // Karfreitag
            $easter->modify('+1 day')->format('Y-m-d'),   // Ostermontag
            $easter->modify('+39 days')->format('Y-m-d'), // Christi Himmelfahrt
            $easter->modify('+50 days')->format('Y-m-d'), // Pfingstmontag
        ];
    }
}
