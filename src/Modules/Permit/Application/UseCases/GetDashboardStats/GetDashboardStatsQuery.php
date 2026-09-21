<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetDashboardStats;

use App\SharedKernel\Application\Query\QueryInterface;

final readonly class GetDashboardStatsQuery implements QueryInterface
{
    public function __construct(
        public string $filterStart,
        public string $filterEnd,
        public string $filterType,
        public string $searchQuery,
        public int $minArchiveYear,
    ) {
    }
}
