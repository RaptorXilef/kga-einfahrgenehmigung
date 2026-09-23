<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetDashboardStats;

final readonly class DashboardStatsDto
{
    public function __construct(
        public array $periodStats,
        public array $yearlyStats,
        public array $chartDataPayload,
        public array $periodVehicleStats, // Vollständig aufbereitete Fahrzeug-Auswertung für die View
        public array $yearlyVehicleStats, // Array-Map (Jahr => Fahrzeug-Auswertung)
    ) {
    }
}
