<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetDashboardPermits;

/**
 * Kapselt die Liste der Permits für den aktuellen Tab PLUS alle Badge-Zähler für die Navigation.
 */
final readonly class DashboardPermitsResultDto
{
    public function __construct(
        /**
         * @var DashboardPermitDto[]
         */
        public array $items,
        public int $totalItems,
        public int $countActive,
        public int $countFuture,
        public int $countExpired,
        public int $countCancelled,
        public int $countUnpaid,
    ) {
    }
}
