<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Config\ConfigInterface;

/**
 * Service zur Prüfung von Feiertagen und zeitlichen Befahrungsbeschränkungen.
 *
 * Berechnet Schließtage und dynamische Feiertage (Osterzyklus für Berlin) und gleicht sie
 * mit den in der Konfiguration hinterlegten Öffnungszeiten-Slots ab.
 * Kontext: Kern-Validierungskomponente für temporäre Zufahrtsrechte und Kontrollanzeigen.
 *
 * Path: src/Core/Service/HolidayService.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class HolidayService
{
    public function __construct(private ConfigInterface $config)
    {
    }

    /**
     * Ermittelt das nächstmögliche reguläre Zeitfenster für eine Einfahrt innerhalb der nächsten 14 Tage.
     * Iteriert tageweise, überspringt Schließtage und sucht den zeitlich nächsten Slot-Start.
     *
     * @param \DateTimeImmutable $now Der aktuelle Bezugs-Zeitstempel für die Berechnung.
     *
     * @return \DateTimeImmutable|null Der Start-Zeitstempel des nächsten erlaubten Fensters oder null.
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
     * Prüft, ob ein bestimmtes Datum ein Sperrtag (Feiertag oder generell geschlossen) ist.
     *
     * @param \DateTimeImmutable $date Das zu prüfende Datum.
     *
     * @return bool True, wenn am Ziel-Datum keine Einfahrt erlaubt ist.
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
     * Abfrage-Schnittstelle zur Echtzeitvalidierung des aktuellen Zeitpunkts.
     * Prüft, ob JETZT ein Restricted Day vorliegt oder ob die aktuelle Uhrzeit in einem Freigabe-Slot liegt.
     *
     * @return bool True, wenn eine Befahrung zum aktuellen Zeitpunkt zulässig ist.
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
     * Erstellt eine textuelle Zusammenfassung der heutigen Einfahrtsfenster für UI-Meldungen.
     *
     * @return string Formatierter Text der heutigen Slots (z.B. "08:00 - 12:00 Uhr") oder Absage.
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
     * Berechnet alle gesetzlichen Feiertage für das Land Berlin im Zieljahr inklusive variabler Osterfeiertage.
     *
     * @param int $year Das Berechnungsjahr (z.B. 2026).
     *
     * @return array<int, string> Liste von Datumsstrings im ISO-Format 'Y-m-d'.
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

    /**
     * Generiert eine vollständige, strukturierte Wochenübersicht aller regulären Öffnungszeiten.
     *
     * Erstellt eine detaillierte Textmatrix aller erlaubten Einfahrtszeiten.
     * Ausgabeformat: Mo - Fr: 08:00 - 12:00, 15:00 - 20:00 | Sat: ... | Sun: Geschlossen
     *
     * @return string Mit Pipes getrennter String aller Wochentage (Mo - So) und deren Slots für Print-Views/Infoseiten.
     */
    public function getGeneralOpeningHoursText(): string
    {
        $hours = $this->config->get('opening_hours', []);
        if (empty($hours)) {
            return 'nach Vereinbarung';
        }

        $daysMap = [
            'mon' => 'Mo',
            'tue' => 'Di',
            'wed' => 'Mi',
            'thu' => 'Do',
            'fri' => 'Fr',
            'sat' => 'Sa',
            'sun' => 'So',
        ];

        $resultStrings = [];

        foreach ($daysMap as $key => $label) {
            $slots = $hours[$key] ?? [];
            if ($slots === []) {
                $resultStrings[] = "{$label}: Keine Einfahrt";

                continue;
            }

            $daySlots = [];
            foreach ($slots as $slot) {
                $daySlots[] = $slot[0] . ' - ' . $slot[1];
            }
            $resultStrings[] = "{$label}: " . \implode(', ', $daySlots);
        }

        // Formatiert die Ausgabe leserlich mit Trennstrichen
        return \implode(' | ', $resultStrings);
    }
}
