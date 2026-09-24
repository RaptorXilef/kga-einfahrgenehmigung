<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\ViewDashboard;

/**
 * Der allumfassende Read-Model-Container für das Admin-Dashboard.
 * Enthält keinerlei Logik, nur fertig aufbereitete Strings, Booleans und Sub-DTOs.
 */
final readonly class DashboardViewDto
{
    public function __construct(
        public DashboardPermissionsDto $permissions,
        /**
         * @var array<string, DashboardTabStateDto>
         */
        public array $tabStates,
        public ControlBarViewDto $controlBar,
        /**
         * @var CollectiveTransferViewDto[]
         */
        public array $collectiveTransfers,
        /**
         * @var \App\Modules\Permit\Application\UseCases\GetDashboardPermits\DashboardPermitDto[]
         */
        public array $activePermitsDto,
        /**
         * @var \App\Modules\Permit\Application\UseCases\GetDashboardPermits\DashboardPermitDto[]
         */
        public array $futurePermitsDto,
        /**
         * @var \App\Modules\Permit\Application\UseCases\GetDashboardPermits\DashboardPermitDto[]
         */
        public array $expiredPermitsDto,
        /**
         * @var \App\Modules\Permit\Application\UseCases\GetDashboardPermits\DashboardPermitDto[]
         */
        public array $cancelledPermitsDto,
        /**
         * @var \App\Modules\Permit\Application\UseCases\GetFinanceList\FinancePermitDto[]
         */
        public array $financePermitsDto,
        public int $totalActive,
        public int $totalFuture,
        public int $totalExpired,
        public int $totalCancelled,
        public int $totalUnpaid,
        public string $paginationHtmlActive,
        public string $paginationHtmlFuture,
        public string $paginationHtmlExpired,
        public string $paginationHtmlCancelled,
        public string $paginationHtmlFinance,
        public int $financeTableColspan,
        public string $focus,
        public bool $showBankWizard,
        public int $minArchiveYear,
        public string $expiredLoadArchiveUrl,
    ) {
    }
}
