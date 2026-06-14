<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Config\ConfigInterface;
use App\Core\Entity\Permit;

/**
 * Service für die Auswertung, Gruppierung und Bereitstellung von Statistik-Daten für das Dashboard.
 *
 * Path: src/Core/Service/ReportingService.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class ReportingService
{
    public function __construct(
        private ConfigInterface $config,
    ) {
    }

    // --- Dashboard Data Bundlers ---

    /**
     * Wichtigste Dashboard-Funktion: Teilt Daten in die Tabs auf
     *
     * Gruppiert alle Genehmigungen in 'active', 'future', 'expired' und 'unpaid'.
     *
     * @param array $allPermits Array aller Permit-Entitäten.
     *
     * @return array<string, array<int, Permit>> Assoziatives Array mit den gebündelten Genehmigungen.
     */
    public function groupPermits(array $allPermits): array
    {
        $now    = new \DateTimeImmutable('today');
        $groups = [
            'active'  => [],
            'future'  => [],
            'expired' => [],
            'unpaid'  => [],
        ];

        foreach ($allPermits as $permit) {
            // 1. FINANZ-LOGIK (Unbezahlte sammeln)
            // Wir prüfen auf 'bezahlt'. Alles andere (offen, leer, NULL)
            // gilt als "unbezahlt" und landet im Finanz-Tab.

            // Tell, don't ask! Wir fragen die Entität nach ihrem Zustand.
            if (! $permit->isPaid()) {
                $groups['unpaid'][] = $permit;
            }

            // Zeit-Logik
            if ($permit->isExpired($now)) {
                $groups['expired'][] = $permit;

                continue;
            }

            if ($permit->isFuture($now)) {
                $groups['future'][] = $permit;

                continue;
            }

            $groups['active'][] = $permit;
        }

        // 3. SORTIERUNG FÜR FINANZEN
        // Die neuesten Anträge (erstellt am) sollen oben stehen.
        \usort($groups['unpaid'], fn ($a, $b): int => $b->erstellt <=> $a->erstellt);

        return $groups;
    }

    // --- Metric Calculation Engines ---

    /**
     * Berechnet Umsätze und Plot-Statistiken für Filterzeiträume
     *
     * Berechnet tiefergehende Umsatz- und Fahrzeugstatistiken für einen gefilterten Zeitraum.
     *
     * @param array<int, Permit> $permits Array der zu berücksichtigenden Permit-Entitäten.
     *
     * @return array<string, mixed> Metriken wie Umsatz, Fahrzeugtypen-Count und Ranking nach Parzellen.
     */
    public function calculateDetailedStats(array $permits): array
    {
        $vConfig = $this->config->get('vehicle_types', []);

        // Initialisierung inklusive Legacy-Speicher
        // Initialisiert das Array mit allen Keys aus der Config (pkw, lkw, entsorg, etc.)
        $typeStats = \array_fill_keys(\array_keys($vConfig), 0);

        // Ein Sammelbecken für gelöschte Typen
        $typeStats['__legacy__'] = 0;

        // [x] sortiert
        $stats = [
            'count'          => \count($permits),
            'plots'          => [],
            'revenue_paid'   => 0.0,
            'revenue_unpaid' => 0.0,
            'types'          => $typeStats,
        ];

        foreach ($permits as $p) {
            // Fahrzeugtypen zählen
            $pType = $p->getVehicleType();
            $pNum  = $p->getPlotNumber();
            $price = $p->getPrice();

            // Wenn Typ existiert, normal zählen, sonst in den Legacy-Topf
            if (isset($stats['types'][$pType])) {
                ++$stats['types'][$pType];
            } else {
                // Falls der Typ aus der Config gelöscht wurde,
                // zählen wir ihn hier rein, damit die Summe stimmt.
                ++$stats['types']['__legacy__'];
            }

            // Initialisiere Parzelle im Ranking, falls noch nicht vorhanden
            if (! isset($stats['plots'][$pNum])) {
                $stats['plots'][$pNum] = [
                    'count'   => 0,
                    'revenue' => 0.0,
                    'email'   => '',
                    'name'    => '',
                ];
            }

            // Daten aggregieren
            ++$stats['plots'][$pNum]['count'];
            $stats['plots'][$pNum]['revenue'] += $price;

            // Zuletzt verwendete Daten speichern
            $stats['plots'][$pNum]['name']  = $p->getOwnerName();
            $stats['plots'][$pNum]['email'] = $p->getOwnerEmail();

            // Umsätze berechnen
            if ($p->isPaid()) { // Sauber!
                $stats['revenue_paid'] += $price;
            } else {
                $stats['revenue_unpaid'] += $price;
            }
        }

        // Parzellen nach Umsatz und Anzahl absteigend sortieren
        \uasort(
            $stats['plots'],
            fn ($a, $b): int => $b['count'] === $a['count']
                ? $b['revenue'] <=> $a['revenue']
                : $b['count'] <=> $a['count'],
        );

        // Typen-Prüfung eingebaut, um PHP-Notice bei komplett leeren Filterergebnissen zu unterbinden
        $maxCount = (
            ! empty($stats['plots'])
            && \is_array(\reset($stats['plots']))
            && \reset($stats['plots'])['count'] > 0
        )
            ? \reset($stats['plots'])['count']
            : 1;

        // Den berechneten Wert dem Array hinzufügen!
        $stats['max_plot_count'] = $maxCount;

        return $stats;
    }

    /**
     * Berechnet die historischen Jahresabschlüsse
     *
     * Gruppiert und berechnet die Finanz- und Antrags-Statistiken nach Jahren.
     *
     * @param array $allPermits Array aller Permit-Entitäten.
     *
     * @return array Assoziatives Array, indexiert nach Jahreszahlen (Y).
     */
    public function calculateYearlyStats(array $allPermits): array
    {
        $yearlyStats = [];
        $vConfig     = $this->config->get('vehicle_types', []);

        foreach ($allPermits as $p) {
            $year = $p->erstellt->format('Y');
            if (! isset($yearlyStats[$year])) {
                $yearlyStats[$year] = [
                    'count'  => 0,
                    'paid'   => 0.0,
                    'unpaid' => 0.0,
                    'types'  => \array_fill_keys(\array_keys($vConfig), 0),
                ];
                $yearlyStats[$year]['types']['__legacy__'] = 0;
            }

            ++$yearlyStats[$year]['count'];
            // Dynamisches Zählen des Fahrzeugtyps
            $pType = $p->getVehicleType();
            $price = $p->getPrice();

            if (isset($yearlyStats[$year]['types'][$pType])) {
                ++$yearlyStats[$year]['types'][$pType];
            } else {
                ++$yearlyStats[$year]['types']['__legacy__'];
            }

            if ($p->isPaid()) {
                $yearlyStats[$year]['paid'] += $price;
            } else {
                $yearlyStats[$year]['unpaid'] += $price;
            }
        }

        \krsort($yearlyStats);

        return $yearlyStats;
    }
}
