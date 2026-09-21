<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetDashboardStats;

use App\Contracts\Config\ConfigInterface;
use App\SharedKernel\Application\Query\QueryHandlerInterface;
use PDO;

/**
 * @implements QueryHandlerInterface<GetDashboardStatsQuery, DashboardStatsDto>
 */
final readonly class GetDashboardStatsHandler implements QueryHandlerInterface
{
    public function __construct(
        private PDO $pdo,
        private ConfigInterface $config,
    ) {
    }

    /**
     * @param GetDashboardStatsQuery $query
     */
    public function handle(mixed $query): DashboardStatsDto
    {
        $vConfig = $this->config->get('vehicle_types', []);
        $permitTemplates = $this->config->get('permit_templates', []);

        // 1. Schlanker PDO Fetch über beide Tabellen (ohne schwere Entity Hydration!)
        $sql = '
            SELECT template_key, typ, status, preis, erstellt, parzelle, name, email, kennzeichen, zweck
            FROM permits
            UNION ALL
            SELECT template_key, typ, status, preis, erstellt, parzelle, name, email, kennzeichen, zweck
            FROM permits_archive
            WHERE YEAR(erstellt) >= :minArchiveYear OR YEAR(von) >= :minArchiveYear
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['minArchiveYear' => $query->minArchiveYear]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // 2. Initialisiere leere Statistik-Container
        $periodStats = [
            'count' => 0, 'revenue_paid' => 0.0, 'revenue_unpaid' => 0.0,
            'types' => \array_fill_keys(\array_keys($vConfig), 0), 'plots' => [],
        ];
        $periodStats['types']['__legacy__'] = 0;

        $yearlyStats = [];
        $monthlyStats = [];
        $queryLower = \strtolower(\trim($query->searchQuery));

        // 3. Ein einziger High-Speed Loop durch alle Datensätze
        foreach ($rows as $row) {
            $date = \substr($row['erstellt'], 0, 10); // Y-m-d
            $status = $row['status'];
            $typ = $row['typ'];
            $price = (float) $row['preis'];
            $year = \substr($row['erstellt'], 0, 4);
            $monthKey = \substr($row['erstellt'], 5, 2) . '.' . $year; // m.Y
            $monthSortKey = \substr($row['erstellt'], 0, 7); // Y-m

            // ---- A) Globale Jahresstatistiken (Für Akkordeon) ----
            if (!isset($yearlyStats[$year])) {
                $yearlyStats[$year] = [
                    'count' => 0, 'paid' => 0.0, 'unpaid' => 0.0,
                    'types' => \array_fill_keys(\array_keys($vConfig), 0),
                ];
                $yearlyStats[$year]['types']['__legacy__'] = 0;
            }
            ++$yearlyStats[$year]['count'];
            isset($yearlyStats[$year]['types'][$typ]) ? $yearlyStats[$year]['types'][$typ]++ : $yearlyStats[$year]['types']['__legacy__']++;
            if ($status === 'bezahlt') {
                $yearlyStats[$year]['paid'] += $price;
            } else {
                $yearlyStats[$year]['unpaid'] += $price;
            }

            // ---- B) Perioden-Filter anwenden für Top-Cards und Rankings ----
            $inPeriod = $date >= $query->filterStart && $date <= $query->filterEnd;
            $typeMatch = $query->filterType === 'all' || (($permitTemplates[$row['template_key']]['type'] ?? 'standard') === $query->filterType);

            $searchMatch = true;
            if ($queryLower !== '') {
                $searchString = \strtolower($row['name'] . ' ' . $row['email'] . ' ' . $row['kennzeichen'] . ' ' . \str_pad((string) $row['parzelle'], 4, '0', \STR_PAD_LEFT) . ' ' . $row['zweck']);
                $searchMatch = \str_contains($searchString, $queryLower);
            }

            if ($inPeriod && $typeMatch && $searchMatch) {
                ++$periodStats['count'];
                isset($periodStats['types'][$typ]) ? $periodStats['types'][$typ]++ : $periodStats['types']['__legacy__']++;

                if ($status === 'bezahlt') {
                    $periodStats['revenue_paid'] += $price;
                } else {
                    $periodStats['revenue_unpaid'] += $price;
                }

                $pNum = \str_pad((string) $row['parzelle'], 4, '0', \STR_PAD_LEFT);
                $periodStats['plots'][$pNum] ??= ['count' => 0, 'revenue' => 0.0, 'name' => $row['name']];
                ++$periodStats['plots'][$pNum]['count'];
                $periodStats['plots'][$pNum]['revenue'] += $price;

                // ---- C) Monats-Statistiken für den Chart (NUR gefilterte Daten) ----
                $monthlyStats[$monthKey] ??= ['sort_key' => $monthSortKey, 'count' => 0, 'revenue' => 0.0];
                ++$monthlyStats[$monthKey]['count'];
                $monthlyStats[$monthKey]['revenue'] += $price;
            }
        }

        // 4. Sortierungen anwenden
        \krsort($yearlyStats);
        \uasort($periodStats['plots'], fn ($a, $b) => $b['count'] === $a['count'] ? $b['revenue'] <=> $a['revenue'] : $b['count'] <=> $a['count']);
        \uasort($monthlyStats, fn ($a, $b) => $a['sort_key'] <=> $b['sort_key']);

        $periodStats['max_plot_count'] = !empty($periodStats['plots']) ? \reset($periodStats['plots'])['count'] : 1;

        // 5. Payload für Chart.js aufbereiten
        $chartDataPayload = [
            'yearLabels' => \array_reverse(\array_keys($yearlyStats)),
            'yearRevenue' => \array_reverse(\array_map(fn ($d) => $d['paid'] + $d['unpaid'], $yearlyStats)),
            'yearCounts' => \array_reverse(\array_map(fn ($d) => $d['count'], $yearlyStats)),
            'monthLabels' => \array_values(\array_keys($monthlyStats)),
            'monthRevenue' => \array_values(\array_map(fn ($d) => $d['revenue'], $monthlyStats)),
            'monthCounts' => \array_values(\array_map(fn ($d) => $d['count'], $monthlyStats)),
        ];

        return new DashboardStatsDto($periodStats, $yearlyStats, $chartDataPayload);
    }
}
