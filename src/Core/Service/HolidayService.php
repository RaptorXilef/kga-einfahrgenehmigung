<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Config\ConfigInterface;

/**
 * TODO Phase 3 nicht nötig
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
                    $slotStart = $current->setTime((int) \substr((string) $slot[0], 0, 2), (int) \substr((string) $slot[0], 3, 2));

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
        $dayKey       = \strtolower($date->format('D'));
        $openingHours = $this->config->get('opening_hours', []);

        // Wenn für diesen Tag leere Arrays definiert sind (wie bei 'sun'), ist er gesperrt
        // FIX: empty() durch strikten Vergleich ersetzt
        if (($openingHours[$dayKey] ?? []) === []) {
            return true;
        }

        // Neue zentrale Logik abrufen
        $allHolidays = $this->getAllHolidaysForYear((int) $date->format('Y'));

        // Feiertags-Check (Berlin)
        return \in_array(
            $date->format('Y-m-d'),
            $allHolidays,
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
     * TODO ggf Löschen, wurde abgelöst von getStateHolidays
     * Berechnet alle gesetzlichen Feiertage für das Land Berlin im Zieljahr inklusive variabler Osterfeiertage.
     *
     * @param int $year Das Berechnungsjahr (z.B. 2026).
     *
     * @return array<int, string> Liste von Datumsstrings im ISO-Format 'Y-m-d'.
     *
     * private function getBerlinHolidays(int $year): array
     * {
     * $easter = \DateTimeImmutable::createFromFormat('U', (string) \easter_date($year));
     *
     * return [
     * $year . '-01-01', // Neujahr
     * $year . '-03-08', // Frauentag (Berlin Spezial)
     * $year . '-05-01', // Tag der Arbeit
     * $year . '-10-03', // Tag der Deutschen Einheit
     * $year . '-12-25', // 1. Weihnachtstag
     * $year . '-12-26', // 2. Weihnachtstag
     * // Bewegliche Feiertage via Ostern:
     * $easter->modify('-2 days')->format('Y-m-d'),  // Karfreitag
     * $easter->modify('+1 day')->format('Y-m-d'),   // Ostermontag
     * $easter->modify('+39 days')->format('Y-m-d'), // Christi Himmelfahrt
     * $easter->modify('+50 days')->format('Y-m-d'), // Pfingstmontag
     * ];
     * }*/

    /**
     * Berechnet alle gesetzlichen Feiertage basierend auf dem konfigurierten Bundesland.
     * Nutzt `easter_days` und `UTC`, um Sommerzeit-Bugs (DST) z.B. bei Pfingstmontag (+50 Tage) zu verhindern.
     *
     * @param int $year Das Jahr, für das die gesetzlichen Feiertage berechnet werden sollen.
     *
     * @return array<int, string> Liste der Feiertagsdaten im Format 'Y-m-d'.
     */
    private function getStateHolidays(int $year): array
    {
        // UTC verhindert, dass bei +50 Tagen eine Stunde durch Sommerzeit verloren geht und
        // wir am Vortag um 23:00 Uhr landen
        $base   = new \DateTimeImmutable("$year-03-21", new \DateTimeZone('UTC'));
        $easter = $base->modify('+' . \easter_days($year) . ' days');

        // Bundesweit einheitliche Feiertage
        $holidays = [
            $year . '-01-01', // Neujahr
            $year . '-05-01', // Tag der Arbeit
            $year . '-10-03', // Tag der Deutschen Einheit
            $year . '-12-25', // 1. Weihnachtstag
            $year . '-12-26', // 2. Weihnachtstag
            $easter->modify('-2 days')->format('Y-m-d'),  // Karfreitag
            $easter->modify('+1 day')->format('Y-m-d'),   // Ostermontag
            $easter->modify('+39 days')->format('Y-m-d'), // Christi Himmelfahrt
            $easter->modify('+50 days')->format('Y-m-d'), // Pfingstmontag
        ];

        // Bundeslandspezifische Feiertage
        $state = $this->config->get('holiday_check', 'Berlin');

        if (
            \in_array(
                $state,
                [
                    'Baden-Württemberg',
                    'Bayern',
                    'Sachsen-Anhalt',
                ],
                true,
            )
        ) {
            $holidays[] = $year . '-01-06'; // Heilige Drei Könige
        }
        if (
            \in_array(
                $state,
                [
                    'Berlin',
                    'Mecklenburg-Vorpommern',
                ],
                true,
            )
        ) {
            $holidays[] = $year . '-03-08'; // Frauentag
        }
        if (
            \in_array(
                $state,
                [
                    'Baden-Württemberg',
                    'Bayern',
                    'Hessen',
                    'Nordrhein-Westfalen',
                    'Rheinland-Pfalz',
                    'Saarland',
                ],
                true,
            )
        ) {
            $holidays[] = $easter->modify('+60 days')->format('Y-m-d'); // Fronleichnam
        }
        if (
            \in_array(
                $state,
                [
                    'Saarland',
                    'Bayern',
                ],
                true,
            )
        ) {
            $holidays[] = $year . '-08-15'; // Mariä Himmelfahrt
        }
        if ($state === 'Thüringen') {
            $holidays[] = $year . '-09-20'; // Weltkindertag
        }
        if (
            \in_array(
                $state,
                [
                    'Brandenburg',
                    'Bremen',
                    'Hamburg',
                    'Mecklenburg-Vorpommern',
                    'Niedersachsen',
                    'Sachsen',
                    'Sachsen-Anhalt',
                    'Schleswig-Holstein',
                    'Thüringen',
                ],
                true,
            )
        ) {
            $holidays[] = $year . '-10-31'; // Reformationstag
        }
        if (
            \in_array(
                $state,
                [
                    'Baden-Württemberg',
                    'Bayern',
                    'Nordrhein-Westfalen',
                    'Rheinland-Pfalz',
                    'Saarland',
                ],
                true,
            )
        ) {
            $holidays[] = $year . '-11-01'; // Allerheiligen
        }
        if ($state === 'Sachsen') {
            // Buß- und Bettag: Mittwoch vor dem 23. November
            $holidays[] = (new \DateTimeImmutable(
                "$year-11-23",
                new \DateTimeZone('UTC'),
            ))->modify('last wednesday')->format('Y-m-d');
        }

        return $holidays;
    }

    /**
     * Generiert eine vollständige, strukturierte Wochenübersicht aller regulären Öffnungszeiten.
     *
     * Erstellt eine detaillierte Textmatrix aller erlaubten Einfahrtszeiten mit intelligenter Perioden-Zusammenfassung.
     * Ausgabeformat z.B.: Mo - Di, Do - Fr: 08:00 - 13:00 | Mi: ... | So: Keine Einfahrt
     *
     * @return string Mit Pipes getrennter String aller Wochentage (Mo - So) und deren Slots für Print-Views/Infoseiten.
     */
    public function getGeneralOpeningHoursText(): string
    {
        $hours = $this->config->get('opening_hours', []);
        if (empty($hours)) {
            return 'nach Vereinbarung';
        }

        $useFullList = (bool) $this->config->get('holiday_service_use_full_list', false);
        $daysMap     = [
            'mon' => 'Mo',
            'tue' => 'Di',
            'wed' => 'Mi',
            'thu' => 'Do',
            'fri' => 'Fr',
            'sat' => 'Sa',
            'sun' => 'So',
        ];

        if ($useFullList) {
            // Klassische Ansicht: Jeden Tag einzeln auflisten
            $resultStrings = [];
            foreach ($daysMap as $key => $label) {
                $slots = $hours[$key] ?? [];
                if ($slots === []) {
                    $resultStrings[] = "{$label}: Keine Einfahrt";

                    continue;
                }
                $daySlots        = \array_map(fn (array $s): string => $s[0] . ' - ' . $s[1], $slots);
                $resultStrings[] = "{$label}: " . \implode(', ', $daySlots);
            }

            return \implode(' | ', $resultStrings);
        }

        // --- Start der neuen, intelligenten Gruppierung ---

        $chronologicalGroups = [];
        $currentGroup        = null;

        foreach ($daysMap as $key => $label) {
            $slots = $hours[$key] ?? [];
            // Eindeutigen Key für die Timeslots generieren
            $slotKey = empty($slots) ? 'none' : \implode(',', \array_map(
                fn (array $s): string => $s[0] . '-' . $s[1],
                $slots,
            ));

            // Wenn es der erste Tag ist oder sich die Zeiten zum Vortag geändert haben: Neue Gruppe starten
            if ($currentGroup === null || $currentGroup['slotKey'] !== $slotKey) {
                if ($currentGroup !== null) {
                    $chronologicalGroups[] = $currentGroup;
                }
                $currentGroup = [
                    'slotKey' => $slotKey,
                    'slots'   => $slots,
                    'days'    => [$label],
                ];
            } else {
                // Zeiten sind gleich wie am Vortag -> Tag zur aktuellen Gruppe hinzufügen
                $currentGroup['days'][] = $label;
            }
        }
        // Letzte Gruppe sichern
        $chronologicalGroups[] = $currentGroup;

        // Jetzt führen wir Gruppen mit identischen Zeiten zusammen, die chronologisch getrennt wurden
        $finalMerged = [];
        foreach ($chronologicalGroups as $group) {
            $slotKey = $group['slotKey'];

            // Formatierung der Tage für diese Kette (z.B. ["Mo", "Di", "Mi"] -> "Mo - Mi")
            $count = \count($group['days']);
            if ($count === 1) {
                $dayString = $group['days'][0];
            } elseif ($count === 2) {
                $dayString = $group['days'][0] . ', ' . $group['days'][1];
            } else {
                $dayString = $group['days'][0] . ' - ' . $group['days'][$count - 1];
            }

            if (! isset($finalMerged[$slotKey])) {
                $finalMerged[$slotKey] = [
                    'dayParts' => [$dayString],
                    'slots'    => $group['slots'],
                ];
            } else {
                // Gleiche Zeiten gab es schon mal (z.B. Mo-Di und Do-Fr haben dieselbe Zeit)
                $finalMerged[$slotKey]['dayParts'][] = $dayString;
            }
        }

        // Finale Text-Generierung
        $finalParts = [];
        foreach ($finalMerged as $slotKey => $data) {
            // Verbindet getrennte Ketten sauber mit Komma (z.B. "Mo - Di, Do - Fr")
            $daysText = \implode(', ', $data['dayParts']);

            if ($slotKey === 'none') {
                $finalParts[] = "{$daysText}: Keine Einfahrt";
            } else {
                $slotStrings  = \array_map(fn (array $s): string => $s[0] . ' - ' . $s[1], $data['slots']);
                $finalParts[] = "{$daysText}: " . \implode(', ', $slotStrings);
            }
        }

        return \implode(' | ', $finalParts);
    }

    /**
     * Generiert eine Liste von Feier- und Ruhetagen, wobei zusammenhängende Tage
     * zu Bereichen (z.B. 24.12. - 26.12.2026) zusammengefasst werden.
     *
     * @param \DateTimeImmutable $von        Startdatum des Zeitraums.
     * @param \DateTimeImmutable $bis        Enddatum des Zeitraums.
     * @param bool               $withPrefix Ob der erklärende Satz davor stehen soll.
     *
     * @return string Formatierte Liste oder Bereiche der Feiertage.
     */
    public function getHolidaysInRangeText(
        \DateTimeImmutable $von,
        \DateTimeImmutable $bis,
        bool $withPrefix = true,
    ): string {
        $startYear = (int) $von->format('Y');
        $endYear   = (int) $bis->format('Y');
        $holidays  = [];

        for ($year = $startYear; $year <= $endYear; ++$year) {
            // Neue zentrale Logik abrufen
            $yearlyHolidays = $this->getAllHolidaysForYear($year);

            foreach ($yearlyHolidays as $dateStr) {
                $date = new \DateTimeImmutable($dateStr);
                // Prüfen, ob der Feiertag in den Gültigkeitszeitraum fällt
                if ($date < $von->setTime(0, 0, 0) || $date > $bis->setTime(23, 59, 59)) {
                    continue;
                }

                $holidays[] = $date->format('Y-m-d');
            }
        }

        // Wenn gar kein Feiertag im Zeitraum liegt, wird ein Leerstring zurückgegeben.
        // Das UI blendet die Anzeige dann komplett aus.
        if ($holidays === []) {
            return '';
        }

        // Chronologisch sortieren und formatieren
        \sort($holidays);
        $holidays = \array_unique($holidays);

        $formattedRanges = $this->formatDateRanges($holidays);
        $dateString      = \implode(', ', $formattedRanges);

        if (! $withPrefix) {
            return $dateString;
        }

        return '🚫 An folgenden Feier- und Ruhetagen ist die Einfahrt untersagt:<br>' . $dateString . '.';
    }

    /**
     * Führt automatische bundeslandspezifische Feiertage und manuelle Konfigurations-Feiertage zusammen.
     * Bereinigt die Liste von Duplikaten und validiert Datumsformate.
     *
     * @param int $year Das Jahr, für das die komplette Feiertagsliste benötigt wird.
     *
     * @return array<int, string> Bereinigte Liste aller Feier- und Ruhetage im Format 'Y-m-d'.
     */
    private function getAllHolidaysForYear(int $year): array
    {
        $holidays = [];

        // 1. Automatische Feiertage des Bundeslandes laden
        if ($this->config->get('use_auto_holidays', true)) {
            $holidays = $this->getStateHolidays($year);
        }

        // 2. Eigene Feiertage aus der Config laden (Fehlerrobustes Parsing)
        $customHolidays = $this->config->get('custom_holidays', []);
        foreach ($customHolidays as $customDate) {
            $cleanDate = \str_replace('.', '-', $customDate); // Macht aus 26.05.2026 -> 26-05-2026
            $time      = \strtotime($cleanDate);
            if ($time === false) {
                continue;
            }

            $parsedDate = \date('Y-m-d', $time);
            // Nur übernehmen, wenn es das angefragte Jahr betrifft
            if (! \str_starts_with($parsedDate, (string) $year)) {
                continue;
            }

            $holidays[] = $parsedDate;
        }

        // Duplikate entfernen (falls ein Custom-Date zufällig auf einen Feiertag fällt)
        return \array_unique($holidays);
    }

    /**
     * Gruppiert eine Liste von Datums-Strings in kompakte Bereiche.
     *
     * @param array $dates Liste von Daten im Format 'Y-m-d'.
     *
     * @return array Liste von formatierten Einzeldaten oder Bereichen.
     */
    private function formatDateRanges(array $dates): array
    {
        if ($dates === []) {
            return [];
        }

        $ranges  = [];
        $start   = $current = new \DateTimeImmutable($dates[0]);
        $counter = \count($dates);

        for ($i = 1; $i <= $counter; ++$i) {
            $next = isset($dates[$i]) ? new \DateTimeImmutable($dates[$i]) : null;

            // Prüfen, ob der nächste Tag direkt auf den aktuellen folgt
            if ($next instanceof \DateTimeImmutable && $next->modify('-1 day')->format('Y-m-d') === $current->format('Y-m-d')) {
                $current = $next;
            } else {
                // Bereich abschließen
                if ($start->format('Y-m-d') === $current->format('Y-m-d')) {
                    $ranges[] = $start->format('d.m.Y');
                } else {
                    // Wenn Jahre gleich sind, evtl. Jahr am Ende sparen (optional),
                    // hier bleiben wir beim Standard-Format für Klarheit
                    $ranges[] = $start->format('d.m.') . ' - ' . $current->format('d.m.Y');
                }
                if ($next instanceof \DateTimeImmutable) {
                    $start = $current = $next;
                }
            }
        }

        return $ranges;
    }
}
