<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetDashboardStats;

use App\Contracts\Config\ConfigInterface;
use App\SharedKernel\Application\Query\QueryHandlerInterface;
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
    public function handle(mixed $query): DashboardStatsDto
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
        $periodStats = [
            'count' => 0,
            'revenue_paid' => 0.0,
            'revenue_unpaid' => 0.0,
            'types' => \array_fill_keys(\array_keys($vConfig), 0),
            'plots' => [],
        ];
        $periodStats['types']['__legacy__'] = 0;

        $yearlyStats = [];
        $monthlyStats = [];
        $queryLower = \strtolower(\trim($query->searchQuery));

        // 3. VSA FIX: Ein einziger High-Speed Loop (Unbuffered/Row-by-Row).
        while (\is_array($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
            $date = \substr((string) $row['erstellt'], 0, 10);
            $status = (string) $row['status'];
            $typ = (string) $row['typ'];
            $price = (float) $row['preis'];
            $year = \substr((string) $row['erstellt'], 0, 4);
            $monthKey = \substr((string) $row['erstellt'], 5, 2) . '.' . $year;
            $monthSortKey = \substr((string) $row['erstellt'], 0, 7);

            // ---- A) Globale Jahresstatistiken (Für Akkordeon) ----
            if (!isset($yearlyStats[$year])) {
                $yearlyStats[$year] = [
                    'count' => 0,
                    'paid' => 0.0,
                    'unpaid' => 0.0,
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

            if (!$inPeriod || !$typeMatch || !$searchMatch) {
                continue;
            }

            ++$periodStats['count'];
            isset($periodStats['types'][$typ]) ? $periodStats['types'][$typ]++ : $periodStats['types']['__legacy__']++;

            if ($status === 'bezahlt') {
                $periodStats['revenue_paid'] += $price;
            } else {
                $periodStats['revenue_unpaid'] += $price;
            }

            $pNum = \str_pad((string) $row['parzelle'], 4, '0', \STR_PAD_LEFT);
            $periodStats['plots'][$pNum] ??= ['count' => 0, 'revenue' => 0.0, 'name' => $row['name'], 'email' => $row['email']];
            ++$periodStats['plots'][$pNum]['count'];
            $periodStats['plots'][$pNum]['revenue'] += $price;
            // Immer die Daten des aktuellsten Antrags merken
            $periodStats['plots'][$pNum]['name'] = $row['name'];
            $periodStats['plots'][$pNum]['email'] = $row['email'];

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
            $rawEmail = \trim((string) ($pData['email'] ?? ''));
            if ($rawEmail !== '' && $rawEmail !== '0') {
                $safeMail = \htmlspecialchars($rawEmail, \ENT_QUOTES, 'UTF-8');
                $wbrMail = \str_replace('@', '<wbr>@', $safeMail);
                $emailHtml = '<small><a href="mailto:' . $safeMail . '" class="u-text-link c-table__mail-link">' . $wbrMail . '</a></small>';
            } else {
                $emailHtml = '<small class="u-color-muted"><em>keine Angabe</em></small>';
            }

            $count = (int) $pData['count'];
            $perc = $count / $maxPlotCount * 100.0;

            $topPlots[] = new PlotRankingItemDto(
                rank: $rank,
                isTopThree: $rank <= 3,
                plotNumber: (string) $pNum,
                ownerName: (string) ($pData['name'] ?: 'Unbekannt'),
                emailHtml: $emailHtml,
                revenueFormatted: \number_format((float) $pData['revenue'], 2, ',', '.') . ' €',
                count: $count,
                intensityPercent: $perc,
            );
            ++$rank;
        }

        // 6. Payload für Chart.js aufbereiten
        $chartDataPayload = [
            'yearLabels' => \array_reverse(\array_keys($yearlyStats)),
            'yearRevenue' => \array_reverse(\array_map(fn (array $d): float => $d['paid'] + $d['unpaid'], $yearlyStats)),
            'yearCounts' => \array_reverse(\array_map(fn (array $d): int => $d['count'], $yearlyStats)),
            'monthLabels' => \array_values(\array_keys($monthlyStats)),
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
                $periodVehicleStats[] = [
                    'count' => $count,
                    'label' => (string) ($vConfig[$typeKey]['label'] ?? $typeKey),
                    'icon' => (string) ($vConfig[$typeKey]['icon'] ?? ''),
                ];
            }
        }

        $yearlyVehicleStats = [];
        $yearlyStatItems = [];
        foreach ($yearlyStats as $year => $data) {
            $statsForYear = [];
            $typeItemsHtml = [];

            foreach ($data['types'] as $tKey => $count) {
                if ($count === 0) {
                    continue;
                }
                if ($tKey === '__legacy__') {
                    $item = ['count' => $count, 'label' => 'Sonstige', 'icon' => 'assets/img/icons/warning.webp'];
                } else {
                    $item = [
                        'count' => $count,
                        'label' => (string) ($vConfig[$tKey]['label'] ?? $tKey),
                        'icon' => (string) ($vConfig[$tKey]['icon'] ?? ''),
                    ];
                }
                $statsForYear[] = $item;

                $imgHtml = $item['icon'] !== ''
                    ? '<img src="' . $baseUrl . $item['icon'] . '" alt="" class="c-icon c-icon--inline u-margin-inline-end-xs">'
                    : '';
                $typeItemsHtml[] = '<span>' . $imgHtml . ' ' . $item['count'] . ' ' . \htmlspecialchars($item['label']) . '</span>';
            }

            $yearlyVehicleStats[$year] = $statsForYear;

            $paid = (float) $data['paid'];
            $unpaid = (float) $data['unpaid'];

            $yearlyStatItems[] = new YearlyStatItemDto(
                year: $year,
                count: (int) $data['count'],
                paidShortFormatted: \number_format($paid, 0, ',', '.') . ' €',
                paidFormatted: \number_format($paid, 2, ',', '.') . ' €',
                unpaidFormatted: \number_format($unpaid, 2, ',', '.') . ' €',
                totalFormatted: \number_format($paid + $unpaid, 2, ',', '.') . ' €',
                vehicleSummaryHtml: \implode(' <span class="u-opacity-50">|</span> ', $typeItemsHtml),
            );
        }

        $dtStart = new DateTimeImmutable($query->filterStart);
        $dtEnd = new DateTimeImmutable($query->filterEnd);

        $revPaid = (float) $periodStats['revenue_paid'];
        $revUnpaid = (float) $periodStats['revenue_unpaid'];

        return new DashboardStatsDto(
            periodStats: $periodStats,
            yearlyStats: $yearlyStats,
            chartDataPayload: $chartDataPayload,
            periodVehicleStats: $periodVehicleStats,
            yearlyVehicleStats: $yearlyVehicleStats,
            filterStartFormatted: $dtStart->format('d.m.Y'),
            filterEndFormatted: $dtEnd->format('d.m.Y'),
            periodTotalCount: (int) $periodStats['count'],
            periodRevenuePaidFormatted: \number_format($revPaid, 2, ',', '.') . ' €',
            periodRevenueUnpaidFormatted: \number_format($revUnpaid, 2, ',', '.') . ' €',
            periodRevenueTotalFormatted: \number_format($revPaid + $revUnpaid, 2, ',', '.') . ' €',
            topPlots: $topPlots,
            yearlyStatItems: $yearlyStatItems,
        );
    }
}
