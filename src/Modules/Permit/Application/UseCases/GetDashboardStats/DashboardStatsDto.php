<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetDashboardStats;

final readonly class DashboardStatsDto
{
    /**
     * @param array<string, mixed> $periodStats
     * @param array<int|string, mixed> $yearlyStats
     * @param array<string, mixed> $chartDataPayload
     * @param array<int, array{count: int, label: string, icon: string}> $periodVehicleStats
     * @param array<int|string, array<int, array{count: int, label: string, icon: string}>> $yearlyVehicleStats
     * @param PlotRankingItemDto[] $topPlots
     * @param YearlyStatItemDto[] $yearlyStatItems
     */
    public function __construct(
        public array $periodStats,
        public array $yearlyStats,
        public array $chartDataPayload,
        public array $periodVehicleStats, // Vollständig aufbereitete Fahrzeug-Auswertung für die View
        public array $yearlyVehicleStats, // Array-Map (Jahr => Fahrzeug-Auswertung)
        public string $filterStartFormatted,
        public string $filterEndFormatted,
        public int $periodTotalCount,
        public string $periodRevenuePaidFormatted,
        public string $periodRevenueUnpaidFormatted,
        public string $periodRevenueTotalFormatted,
        public array $topPlots,
        public array $yearlyStatItems,
    ) {
    }
}
