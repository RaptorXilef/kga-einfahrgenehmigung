<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Config\ConfigInterface;

/**
 * Service zur Prüfung von Feiertagen, Ruhetagen und erlaubten Einfahrtszeiten.
 * Beinhaltet die Gaußsche Osterformel und dynamische Feiertagsberechnung.
 *
 * Berechnet Schließtage und dynamische Feiertage (Osterzyklus für Berlin) und gleicht sie
 * mit den in der Konfiguration hinterlegten Öffnungszeiten-Slots ab.
 * Kontext: Kern-Validierungskomponente für temporäre Einfahrtsrechte und Kontrollanzeigen.
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
     * Berechnet den nächsten Zeitpunkt in der Zukunft, an dem eine Einfahrt erlaubt ist.
     *
     * @param \DateTimeImmutable $now Das Referenzdatum (in der Regel "jetzt").
     *
     * @return \DateTimeImmutable|null Der Startzeitpunkt des nächsten freien Slots oder null.
     */
    public function getNextAvailableSlot(\DateTimeImmutable $now): ?\DateTimeImmutable
    {
        $current = $now;
        // Wir prüfen die nächsten 14 Tage
        for ($i = 0; $i < 14; ++$i) {
            if (! $this->isRestrictedDay($current)) {
                $dayKey = \strtolower($current->format('D'));
                $slots  = $this->getOpeningHoursForDate($current)[$dayKey] ?? [];

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
     * Prüft, ob ein gegebenes Datum ein Ruhetag (Sonntag oder Feiertag) ist.
     *
     * @param \DateTimeImmutable $date Das zu prüfende Datum.
     *
     * @return bool True, wenn das Datum ein Ruhetag ist.
     */
    public function isRestrictedDay(\DateTimeImmutable $date): bool
    {
        $dayKey       = \strtolower($date->format('D'));
        $openingHours = $this->getOpeningHoursForDate($date);

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
     * Ermittelt, ob basierend auf den aktuellen Öffnungszeiten jetzt eine Einfahrt erlaubt ist.
     *
     * @return bool True, wenn das aktuelle Zeitfenster gültig ist.
     */
    public function isTimeAllowedNow(): bool
    {
        $now = new \DateTimeImmutable();
        if ($this->isRestrictedDay($now)) {
            return false;
        }

        $dayKey      = \strtolower($now->format('D'));
        $slots       = $this->getOpeningHoursForDate($now)[$dayKey] ?? [];
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
        $slots  = $this->getOpeningHoursForDate(new \DateTimeImmutable())[$dayKey] ?? [];

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
     * Formatiert ein rohes Array von Öffnungszeiten in den gruppierten HTML-Text.
     */
    private function formatHoursArrayToText(array $hours): string
    {
        if (empty($hours)) {
            return 'nach Vereinbarung';
        }

        $useFullList = (bool) $this->config->get('holiday_service_use_full_list', false);
        $daysMap     = [
            'mon' => 'Mo', 'tue' => 'Di', 'wed' => 'Mi', 'thu' => 'Do',
            'fri' => 'Fr', 'sat' => 'Sa', 'sun' => 'So',
        ];

        if ($useFullList) {
            $resultStrings = [];
            foreach ($daysMap as $key => $label) {
                $slots = $hours[$key] ?? [];
                if ($slots === []) {
                    $resultStrings[] = "<span style=\"white-space: nowrap;\"><strong>{$label}:</strong> Keine Einfahrt</span>";

                    continue;
                }
                $daySlots        = \array_map(fn (array $s): string => $s[0] . ' - ' . $s[1], $slots);
                $resultStrings[] = "<span style=\"white-space: nowrap;\"><strong>{$label}:</strong> " . \implode(', ', $daySlots) . '</span>';
            }

            return \implode(' &nbsp;|&nbsp; ', $resultStrings);
        }

        $chronologicalGroups = [];
        $currentGroup        = null;

        foreach ($daysMap as $key => $label) {
            $slots   = $hours[$key] ?? [];
            $slotKey = empty($slots) ? 'none' : \implode(',', \array_map(
                fn (array $s): string => $s[0] . '-' . $s[1],
                $slots,
            ));

            if ($currentGroup === null || $currentGroup['slotKey'] !== $slotKey) {
                if ($currentGroup !== null) {
                    $chronologicalGroups[] = $currentGroup;
                }
                $currentGroup = ['slotKey' => $slotKey, 'slots' => $slots, 'days' => [$label]];
            } else {
                $currentGroup['days'][] = $label;
            }
        }
        $chronologicalGroups[] = $currentGroup;

        $finalMerged = [];
        foreach ($chronologicalGroups as $group) {
            $slotKey = $group['slotKey'];
            $count   = \count($group['days']);
            if ($count === 1) {
                $dayString = $group['days'][0];
            } elseif ($count === 2) {
                $dayString = $group['days'][0] . ', ' . $group['days'][1];
            } else {
                $dayString = $group['days'][0] . ' - ' . $group['days'][$count - 1];
            }

            if (! isset($finalMerged[$slotKey])) {
                $finalMerged[$slotKey] = ['dayParts' => [$dayString], 'slots' => $group['slots']];
            } else {
                $finalMerged[$slotKey]['dayParts'][] = $dayString;
            }
        }

        $finalParts = [];
        foreach ($finalMerged as $slotKey => $data) {
            $daysText = \implode(', ', $data['dayParts']);

            if ($slotKey === 'none') {
                $finalParts[] = "<span style=\"white-space: nowrap;\"><strong>{$daysText}:</strong> Keine Einfahrt</span>";
            } else {
                $slotStrings  = \array_map(fn (array $s): string => $s[0] . ' - ' . $s[1], $data['slots']);
                $finalParts[] = "<span style=\"white-space: nowrap;\"><strong>{$daysText}:</strong> " . \implode(', ', $slotStrings) . '</span>';
            }
        }

        return \implode(' &nbsp;|&nbsp; ', $finalParts);
    }

    /**
     * Standard-Methode für allgemeine Texte (z.B. im Footer).
     */
    public function getGeneralOpeningHoursText(?\DateTimeInterface $date = null): string
    {
        return $this->formatHoursArrayToText($this->getOpeningHoursForDate($date));
    }

    /**
     * Erstellt einen HTML-kompatiblen String mit allen Zeitblöcken für eine Genehmigung.
     * (Wird direkt im Frontend und PDF gerendert)
     */
    public function getOpeningHoursTextForDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): string
    {
        $blocks  = $this->getOpeningHoursForDateRange($startDate, $endDate);
        $result  = [];
        $isMulti = \count($blocks) > 1;

        foreach ($blocks as $block) {
            $hoursText = $this->formatHoursArrayToText($block['hours']);

            if ($isMulti) {
                // Zeilenumbruch und Datum-Präfix, falls es mehrere Saisons gibt
                $result[] = "<div style=\"margin-bottom: 6px;\"><span style=\"color: var(--primary-color);\">{$block['from']} - {$block['to']}:</span><br>" . $hoursText . '</div>';
            } else {
                $result[] = '<div>' . $hoursText . '</div>';
            }
        }

        return \implode('', $result);
    }

    /**
     * Gibt einen formatierten Text der anstehenden Ruhetage innerhalb eines Zeitraums zurück.
     *
     * @param  \DateTimeImmutable $von        Startdatum.
     * @param  \DateTimeImmutable $bis        Enddatum.
     * @param  bool               $withPrefix Ob Warnhinweis-Präfix ("🚫 An folgenden Feier-...") vorangestellt wird.
     * @return string             Formatierte Ruhetags-Auflistung.
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

    /**
     * Ermittelt die korrekten Öffnungszeiten für ein bestimmtes Datum.
     */
    public function getOpeningHoursForDate(?\DateTimeInterface $date = null): array
    {
        $date ??= new \DateTimeImmutable();
        $seasons = $this->config->get('seasons', []);

        // Wenn Seasons existieren, prüfen in welche wir fallen
        if (! empty($seasons)) {
            $currentDayMonth = $date->format('m-d');

            foreach ($seasons as $season) {
                $start = $season['start'] ?? '01-01';
                $end   = $season['end'] ?? '12-31';

                // Normales Jahr
                if ($start <= $end) {
                    if ($currentDayMonth >= $start && $currentDayMonth <= $end) {
                        return $season['opening_hours'] ?? [];
                    }
                }
                // Jahresübergreifend (z.B. 11-01 bis 02-28)
                else {
                    if ($currentDayMonth >= $start || $currentDayMonth <= $end) {
                        return $season['opening_hours'] ?? [];
                    }
                }
            }
        }

        // Fallback: Wenn keine Saison zutrifft oder keine definiert ist
        return $this->config->get('default_opening_hours', []);
    }

    /**
     * Ermittelt alle zutreffenden Öffnungszeiten für einen kompletten Zeitraum (z.B. eine Genehmigung)
     * und gruppiert diese nach Zeiträumen, falls sich die Saison dazwischen ändert.
     *
     * @param  \DateTimeInterface $startDate Beginn der Genehmigung
     * @param  \DateTimeInterface $endDate   Ende der Genehmigung
     * @return array              Liste von Blöcken mit 'from', 'to' und den jeweiligen 'hours'
     */
    public function getOpeningHoursForDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $intervals = [];
        $current   = clone $startDate;

        $currentIntervalStart = clone $current;
        $lastHours            = null;

        // Wir iterieren Tag für Tag durch die Genehmigung (performant und exakt, auch bei Schaltjahren)
        while ($current <= $endDate) {
            $hoursForDay = $this->getOpeningHoursForDate($current);
            $hoursHash   = \json_encode($hoursForDay); // Einfacher Text-Vergleich der Arrays

            // Wenn sich die Zeiten ändern (Saisonwechsel!) UND wir nicht am allerersten Tag sind
            if ($lastHours !== null && $hoursHash !== $lastHours) {
                $intervals[] = [
                    'from'  => $currentIntervalStart->format('d.m.Y'),
                    'to'    => (clone $current)->modify('-1 day')->format('d.m.Y'),
                    'hours' => \json_decode($lastHours, true),
                ];
                $currentIntervalStart = clone $current; // Start für die neue Saison merken
            }

            $lastHours = $hoursHash;
            $current   = $current->modify('+1 day');
        }

        // Den letzten verbleibenden Zeitblock (bis zum Ende der Genehmigung) anhängen
        if ($lastHours !== null) {
            $intervals[] = [
                'from'  => $currentIntervalStart->format('d.m.Y'),
                'to'    => $endDate->format('d.m.Y'),
                'hours' => \json_decode($lastHours, true),
            ];
        }

        return $intervals;
    }
}
