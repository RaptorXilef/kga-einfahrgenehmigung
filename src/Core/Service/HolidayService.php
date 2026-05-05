<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

/**
 * Path: src/Core/Service/HolidayService.php
 */

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Config\ConfigInterface;

/**
 * Service zur Prüfung von Berliner Feiertagen und Sperrzeiten.
 */
final readonly class HolidayService
{
    public function __construct(private ConfigInterface $config)
    {
    }

    /**
     * Sucht den nächsten befahrbaren Zeitpunkt ab jetzt.
     * Berücksichtigt Wochentage und Feiertage.
     */
    public function getNextAvailableSlot(\DateTimeImmutable $now): ?\DateTimeImmutable
    {
        $current = $now;
        // Wir prüfen die nächsten 14 Tage
        for ($i = 0; $i < 14; ++$i) {
            if (! $this->isRestrictedDay($current)) {
                $dayKey = \strtolower($current->format('D'));
                $slots  = $this->config->get('opening_hours')[$dayKey] ?? [];

                foreach ($slots as $slot) {
                    $slotStart = $current->setTime((int) \substr($slot[0], 0, 2), (int) \substr($slot[0], 3, 2));

                    // Wenn der Slot heute ist, muss er in der Zukunft liegen
                    if ($slotStart > $now) {
                        return $slotStart;
                    }
                }
            }
            // Nächster Tag, Start um 00:00 Uhr
            $current = $current->modify('+1 day')->setTime(0, 0);
        }

        return null;
    }

    /**
     * Prüft, ob ein kompletter Tag gesperrt ist (z.B. Sonntag).
     */
    public function isRestrictedDay(\DateTimeImmutable $date): bool
    {
        $dayKey       = \strtolower($date->format('D')); // 'mon', 'sun', etc.
        $openingHours = $this->config->get('opening_hours', []);

        // Wenn für diesen Tag leere Arrays definiert sind (wie bei 'sun'), ist er gesperrt
        // FIX: empty() durch strikten Vergleich ersetzt
        if (($openingHours[$dayKey] ?? []) === []) {
            return true;
        }

        // Feiertags-Check (Berlin)
        return \in_array(
            $date->format('Y-m-d'),
            $this->getBerlinHolidays((int) $date->format('Y')),
            true,
        );
    }

    /**
     * Prüft, ob die aktuelle Uhrzeit laut Matrix erlaubt ist.
     */
    public function isTimeAllowedNow(): bool
    {
        $now = new \DateTimeImmutable();
        if ($this->isRestrictedDay($now)) {
            return false;
        }

        $dayKey      = \strtolower($now->format('D'));
        $slots       = $this->config->get('opening_hours')[$dayKey] ?? [];
        $currentTime = $now->format('H:i');

        foreach ($slots as $slot) {
            if ($currentTime >= $slot[0] && $currentTime <= $slot[1]) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gibt die erlaubten Zeiten für heute als Text zurück (für die Anzeige)
     */
    public function getTodayAllowedSlots(): string
    {
        $dayKey = \strtolower((new \DateTimeImmutable())->format('D'));
        $slots  = $this->config->get('opening_hours')[$dayKey] ?? [];

        // FIX: empty() durch strikten Vergleich ersetzt
        if ($slots === []) {
            return 'heute keine Einfahrt erlaubt';
        }

        $text = [];
        foreach ($slots as $slot) {
            $text[] = $slot[0] . ' - ' . $slot[1] . ' Uhr';
        }

        return \implode(' & ', $text);
    }

    /**
     * Berechnet alle gesetzlichen Feiertage für Berlin.
     *
     * @return string[]
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
