<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetDashboardStats;

/**
 * 100% logikfreies View-DTO für einen historischen Jahresabschluss im Statistik-Tab.
 */
final readonly class YearlyStatItemDto
{
    public function __construct(
        public string $year,
        public int $count,
        public string $paidShortFormatted,
        public string $paidFormatted,
        public string $unpaidFormatted,
        public string $totalFormatted,
        public string $vehicleSummaryHtml,
    ) {
    }
}
