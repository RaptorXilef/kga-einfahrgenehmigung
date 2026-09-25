<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetDashboardStats;

/**
 * 100% logikfreies View-DTO für eine Zeile im Parzellen-Ranking (Top 30).
 */
final readonly class PlotRankingItemDto
{
    public function __construct(
        public int $rank,
        public bool $isTopThree,
        public string $plotNumber,
        public string $ownerName,
        public string $emailHtml,
        public string $revenueFormatted,
        public int $count,
        public float $intensityPercent,
    ) {
    }
}
