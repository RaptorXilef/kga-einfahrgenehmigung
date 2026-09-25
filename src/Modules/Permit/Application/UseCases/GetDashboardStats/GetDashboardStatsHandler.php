<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetDashboardStats;

use App\Contracts\Config\ConfigInterface;
use App\SharedKernel\Application\Query\QueryHandlerInterface;
use App\SharedKernel\Application\Query\QueryInterface;
use DateTimeImmutable;
use Override;
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
    #[Override]
    public function handle(QueryInterface $query): DashboardStatsDto
    {
        $vConfig = $this->config->getArray('vehicle_types');
        $permitTemplates = $this->config->getArray('permit_templates');
        $baseUrl = \rtrim($this->config->getBaseUrl(), '/') . '/';

        // 1. Schlanker PDO Fetch über beide Tabellen
        $sql = '
            SELECT template_key, typ, status, preis, erstellt, parzelle, name, email, kennzeichen, zweck
            FROM permits
            UNION ALL
            SELECT template_key, typ, status, preis, erstellt, parzelle, name, email, kennzeichen, zweck
            FROM permits_archive
            WHERE YEAR(erstellt) >= :minArchiveYear1 OR YEAR(von) >= :minArchiveYear2
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'minArchiveYear1' => $query->minArchiveYear,
            'minArchiveYear2' => $query->minArchiveYear,
        ]);

        // 2. Initialisiere leere Statistik-Container
        /** @var array<string, int> $initialTypes */
        $initialTypes = \array_fill_keys(\array_map(strval(...), \array_keys($vConfig)), 0);
        $initialTypes['__legacy__'] = 0;

        /**
         * @var array{
         *   count: int,
         *   revenue_paid: float,
         *   revenue_unpaid: float,
         *   types: array<string, int>,
         *   plots: array<int|string, array{count: int, revenue: float, name: string, email: string}>,
         *   max_plot_count?: int
         * } $periodStats
         */
        $periodStats = [
            'count' => 0,
            'revenue_paid' => 0.0,
            'revenue_unpaid' => 0.0,
            'types' => $initialTypes,
            'plots' => [],
        ];

        /**
         * @var array<int|string, array{
         *   count: int,
         *   paid: float,
         *   unpaid: float,
         *   types: array<string, int>
         * }> $yearlyStats
         */
        $yearlyStats = [];

        /** @var array<string, array{sort_key: string, count: int, revenue: float}> $monthlyStats */
        $monthlyStats = [];
        $queryLower = \strtolower(\trim($query->searchQuery));

        // 3. VSA FIX: Ein einziger High-Speed Loop (Unbuffered/Row-by-Row).
        while (\is_array($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
            $erstelltStr = (string) ($row['erstellt'] ?? '');
            $date = \substr($erstelltStr, 0, 10);
            $status = (string) ($row['status'] ?? '');
            $typ = (string) ($row['typ'] ?? '');
            $price = (float) ($row['preis'] ?? 0.0);
            $year = \substr($erstelltStr, 0, 4);
            $monthKey = \substr($erstelltStr, 5, 2) . '.' . $year;
            $monthSortKey = \substr($erstelltStr, 0, 7);
            $tplKey = (string) ($row['template_key'] ?? '');
            $rowName = (string) ($row['name'] ?? '');
            $rowEmail = (string) ($row['email'] ?? '');
            $rowPlate = (string) ($row['kennzeichen'] ?? '');
            $rowPurpose = (string) ($row['zweck'] ?? '');
            $rowPlotFormatted = \str_pad((string) ($row['parzelle'] ?? '0'), 4, '0', \STR_PAD_LEFT);

            // ---- A) Globale Jahresstatistiken (Für Akkordeon) ----
            $yearlyStats[$year] ??= [
                'count' => 0,
                'paid' => 0.0,
                'unpaid' => 0.0,
                'types' => $initialTypes,
            ];
            ++$yearlyStats[$year]['count'];
            if (isset($yearlyStats[$year]['types'][$typ])) {
                ++$yearlyStats[$year]['types'][$typ];
            } else {
                ++$yearlyStats[$year]['types']['__legacy__'];
            }

            if ($status === 'bezahlt') {
                $yearlyStats[$year]['paid'] += $price;
            } else {
                $yearlyStats[$year]['unpaid'] += $price;
            }

            // ---- B) Perioden-Filter anwenden für Top-Cards und Rankings ----
            $inPeriod = $date >= $query->filterStart && $date <= $query->filterEnd;
            $tplCfg = \is_array($permitTemplates[$tplKey] ?? null) ? $permitTemplates[$tplKey] : [];
            $typeMatch = $query->filterType === 'all' || (($tplCfg['type'] ?? 'standard') === $query->filterType);

            $searchMatch = true;
            if ($queryLower !== '') {
                $searchString = \strtolower($rowName . ' ' . $rowEmail . ' ' . $rowPlate . ' ' . $rowPlotFormatted . ' ' . $rowPurpose);
                $searchMatch = \str_contains($searchString, $queryLower);
            }

            if (!$inPeriod || !$typeMatch || !$searchMatch) {
                continue;
            }

            ++$periodStats['count'];
            if (isset($periodStats['types'][$typ])) {
                ++$periodStats['types'][$typ];
            } else {
                ++$periodStats['types']['__legacy__'];
            }

            if ($status === 'bezahlt') {
                $periodStats['revenue_paid'] += $price;
            } else {
                $periodStats['revenue_unpaid'] += $price;
            }

            $periodStats['plots'][$rowPlotFormatted] ??= ['count' => 0, 'revenue' => 0.0, 'name' => $rowName, 'email' => $rowEmail];
            ++$periodStats['plots'][$rowPlotFormatted]['count'];
            $periodStats['plots'][$rowPlotFormatted]['revenue'] += $price;
            // Immer die Daten des aktuellsten Antrags merken
            $periodStats['plots'][$rowPlotFormatted]['name'] = $rowName;
            $periodStats['plots'][$rowPlotFormatted]['email'] = $rowEmail;

            // ---- C) Monats-Statistiken für den Chart (NUR gefilterte Daten) ----
            $monthlyStats[$monthKey] ??= ['sort_key' => $monthSortKey, 'count' => 0, 'revenue' => 0.0];
            ++$monthlyStats[$monthKey]['count'];
            $monthlyStats[$monthKey]['revenue'] += $price;
        }

        // 4. Sortierungen anwenden
        \krsort($yearlyStats);
        \uasort($periodStats['plots'], fn (array $a, array $b): int => $b['count'] === $a['count'] ? $b['revenue'] <=> $a['revenue'] : $b['count'] <=> $a['count']);
        \uasort($monthlyStats, fn (array $a, array $b): int => $a['sort_key'] <=> $b['sort_key']);

        $firstPlot = $periodStats['plots'] !== [] ? \reset($periodStats['plots']) : null;
        $maxPlotCount = \is_array($firstPlot) && $firstPlot['count'] > 0 ? (int) $firstPlot['count'] : 1;
        $periodStats['max_plot_count'] = $maxPlotCount;

        // 5. Top 30 Parzellen-Ranking als logikfreie DTOs aufbereiten
        $topPlots = [];
        $rank = 1;
        foreach (\array_slice($periodStats['plots'], 0, 30, true) as $pNum => $pData) {
            $rawEmail = \trim($pData['email']);
            if ($rawEmail !== '' && $rawEmail !== '0') {
                $safeMail = \htmlspecialchars($rawEmail, \ENT_QUOTES, 'UTF-8');
                $wbrMail = \str_replace('@', '<wbr>@', $safeMail);
                $emailHtml = '<small><a href="mailto:' . $safeMail . '" class="u-text-link c-table__mail-link">' . $wbrMail . '</a></small>';
            } else {
                $emailHtml = '<small class="u-color-muted"><em>keine Angabe</em></small>';
            }

            $count = $pData['count'];
            $perc = $count / $maxPlotCount * 100.0;

            $topPlots[] = new PlotRankingItemDto(
                rank: $rank,
                isTopThree: $rank <= 3,
                plotNumber: (string) $pNum,
                ownerName: $pData['name'] !== '' ? $pData['name'] : 'Unbekannt',
                emailHtml: $emailHtml,
                revenueFormatted: \number_format($pData['revenue'], 2, ',', '.') . ' €',
                count: $count,
                intensityPercent: $perc,
            );
            ++$rank;
        }

        // 6. Payload für Chart.js aufbereiten
        $chartDataPayload = [
            'yearLabels' => \array_map(strval(...), \array_reverse(\array_keys($yearlyStats))),
            'yearRevenue' => \array_reverse(\array_map(fn (array $d): float => $d['paid'] + $d['unpaid'], \array_values($yearlyStats))),
            'yearCounts' => \array_reverse(\array_map(fn (array $d): int => $d['count'], \array_values($yearlyStats))),
            'monthLabels' => \array_map(strval(...), \array_keys($monthlyStats)),
            'monthRevenue' => \array_values(\array_map(fn (array $d): float => $d['revenue'], $monthlyStats)),
            'monthCounts' => \array_values(\array_map(fn (array $d): int => $d['count'], $monthlyStats)),
        ];

        // 7. View-Auflösung der Fahrzeugstatistiken & Jahresabschlüsse
        $periodVehicleStats = [];
        foreach ($periodStats['types'] as $typeKey => $count) {
            if ($count === 0) {
                continue;
            }
            if ($typeKey === '__legacy__') {
                $periodVehicleStats[] = ['count' => $count, 'label' => 'Sonstige / Ehem.', 'icon' => 'assets/img/icons/warning.webp'];
            } else {
                $typeCfg = \is_array($vConfig[$typeKey] ?? null) ? $vConfig[$typeKey] : [];
                $periodVehicleStats[] = [
                    'count' => $count,
                    'label' => (string) ($typeCfg['label'] ?? $typeKey),
                    'icon' => (string) ($typeCfg['icon'] ?? ''),
                ];
            }
        }

        $yearlyVehicleStats = [];
        $yearlyStatItems = [];
        foreach ($yearlyStats as $year => $data) {
            $yearStr = (string) $year;
            $statsForYear = [];
            $typeItemsHtml = [];

            foreach ($data['types'] as $tKey => $count) {
                if ($count === 0) {
                    continue;
                }
                if ($tKey === '__legacy__') {
                    $item = ['count' => $count, 'label' => 'Sonstige', 'icon' => 'assets/img/icons/warning.webp'];
                } else {
                    $tCfg = \is_array($vConfig[$tKey] ?? null) ? $vConfig[$tKey] : [];
                    $item = [
                        'count' => $count,
                        'label' => (string) ($tCfg['label'] ?? $tKey),
                        'icon' => (string) ($tCfg['icon'] ?? ''),
                    ];
                }
                $statsForYear[] = $item;

                $imgHtml = $item['icon'] !== ''
                    ? '<img src="' . $baseUrl . $item['icon'] . '" alt="" class="c-icon c-icon--inline u-margin-inline-end-xs">'
                    : '';
                $typeItemsHtml[] = '<span>' . $imgHtml . ' ' . $item['count'] . ' ' . \htmlspecialchars($item['label']) . '</span>';
            }

            $yearlyVehicleStats[$yearStr] = $statsForYear;

            $paid = $data['paid'];
            $unpaid = $data['unpaid'];

            $yearlyStatItems[] = new YearlyStatItemDto(
                year: $yearStr,
                count: $data['count'],
                paidShortFormatted: \number_format($paid, 0, ',', '.') . ' €',
                paidFormatted: \number_format($paid, 2, ',', '.') . ' €',
                unpaidFormatted: \number_format($unpaid, 2, ',', '.') . ' €',
                totalFormatted: \number_format($paid + $unpaid, 2, ',', '.') . ' €',
                vehicleSummaryHtml: \implode(' <span class="u-opacity-50">|</span> ', $typeItemsHtml),
            );
        }

        $dtStart = new DateTimeImmutable($query->filterStart);
        $dtEnd = new DateTimeImmutable($query->filterEnd);

        $revPaid = $periodStats['revenue_paid'];
        $revUnpaid = $periodStats['revenue_unpaid'];

        return new DashboardStatsDto(
            periodStats: $periodStats,
            yearlyStats: $yearlyStats,
            chartDataPayload: $chartDataPayload,
            periodVehicleStats: $periodVehicleStats,
            yearlyVehicleStats: $yearlyVehicleStats,
            filterStartFormatted: $dtStart->format('d.m.Y'),
            filterEndFormatted: $dtEnd->format('d.m.Y'),
            periodTotalCount: $periodStats['count'],
            periodRevenuePaidFormatted: \number_format($revPaid, 2, ',', '.') . ' €',
            periodRevenueUnpaidFormatted: \number_format($revUnpaid, 2, ',', '.') . ' €',
            periodRevenueTotalFormatted: \number_format($revPaid + $revUnpaid, 2, ',', '.') . ' €',
            topPlots: $topPlots,
            yearlyStatItems: $yearlyStatItems,
        );
    }
}
