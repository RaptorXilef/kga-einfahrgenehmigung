<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Config\ConfigInterface;
use App\Core\Entity\Permit;

/**
 * TODO DocBlock erstellen
 */
final readonly class ReportingService
{
    public function __construct(private ConfigInterface $config)
    {
    }

    /**
     * Gruppiert Genehmigungen nach ihrem aktuellen Status.
     *
     * (aktiv/future/expired/unpaid).
     * Logik-Kern für die tabellarische Übersicht.
     *
     * @param array<int, Permit> $allPermits
     *
     * @return array<string, array<int, Permit>>
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
            if (\strtolower(\trim($permit->status->current)) !== 'bezahlt') {
                $groups['unpaid'][] = $permit;
            }

            // Zeit-Logik
            if ($permit->validity->bis < $now) {
                $groups['expired'][] = $permit;

                continue;
            }
            if ($permit->validity->von > $now) {
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

    /**
     * Berechnet detaillierte Finanz-, Fahrzeugtyp- und Parzellen-Statistiken.
     *
     * Finanz-KPIs (Revenue) und Parzellen-Ranking
     * Aggregiert Daten aus Permit-Array. Nutzt uasort zur Sortierung nach Plot-Ranking.
     *
     * @param array<int, Permit> $permits
     *
     * @return array<string, mixed>
     */
    public function calculateDetailedStats(array $permits): array
    {
        $vConfig = $this->config->get('vehicle_types', []);

        // Initialisierung inklusive Legacy-Speicher
        // Initialisiert das Array mit allen Keys aus der Config (pkw, lkw, entsorg, etc.)
        $typeStats = \array_fill_keys(\array_keys($vConfig), 0);

        // NEU: Ein Sammelbecken für gelöschte Typen
        $typeStats['__legacy__'] = 0;

        $stats = [
            'count'          => \count($permits),
            'revenue_paid'   => 0.0,
            'revenue_unpaid' => 0.0,
            'types'          => $typeStats,
            'plots'          => [],
        ];

        foreach ($permits as $p) {
            // Fahrzeugtypen zählen
            $pType = $p->vehicle->typ;

            // Wenn Typ existiert, normal zählen, sonst in den Legacy-Topf
            if (isset($stats['types'][$pType])) {
                ++$stats['types'][$pType];
            } else {
                // Falls der Typ aus der Config gelöscht wurde,
                // zählen wir ihn hier rein, damit die Summe stimmt.
                ++$stats['types']['__legacy__'];
            }

            // Parzellen aggregieren
            $pNum = $p->owner->parzelle;

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
            $stats['plots'][$pNum]['revenue'] += $p->validity->preisSnapshot;

            // Zuletzt verwendete Daten speichern
            $stats['plots'][$pNum]['name']  = $p->owner->name;
            $stats['plots'][$pNum]['email'] = $p->owner->email;

            // Umsätze berechnen
            if (\strtolower($p->status->current) === 'bezahlt') {
                $stats['revenue_paid'] += $p->validity->preisSnapshot;
            } else {
                $stats['revenue_unpaid'] += $p->validity->preisSnapshot;
            }
        }

        // Parzellen nach Umsatz und Anzahl absteigend sortieren
        \uasort(
            $stats['plots'],
            fn ($a, $b): int => $b['count'] === $a['count']
                ? $b['revenue'] <=> $a['revenue']
                : $b['count'] <=> $a['count'],
        );

        return $stats;
    }
}
