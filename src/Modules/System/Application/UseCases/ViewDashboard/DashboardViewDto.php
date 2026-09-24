<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\ViewDashboard;

use App\Modules\Permit\Application\UseCases\GetDashboardPermits\DashboardPermitDto;
use App\Modules\Permit\Application\UseCases\GetFinanceList\FinancePermitDto;
use App\Modules\Permit\Application\UseCases\GetGeneratorToolsData\GeneratorToolsViewDto;
use App\Modules\System\Application\UseCases\GetBackupsData\BackupsResultDto;
use App\Modules\System\Application\UseCases\GetMailLogsData\MailLogsResultDto;

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
         * @var DashboardPermitDto[]
         */
        public array $activePermitsDto,
        /**
         * @var DashboardPermitDto[]
         */
        public array $futurePermitsDto,
        /**
         * @var DashboardPermitDto[]
         */
        public array $expiredPermitsDto,
        /**
         * @var DashboardPermitDto[]
         */
        public array $cancelledPermitsDto,
        /**
         * @var FinancePermitDto[]
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
        public string $paginationHtmlLogs,
        public int $financeTableColspan,
        public string $focus,
        public bool $showBankWizard,
        public int $minArchiveYear,
        public string $expiredLoadArchiveUrl,

        // --- Die neuen Dumb View DTOs für die System-Tabs ---
        public ?GeneratorToolsViewDto $generatorTools,
        public ?MailLogsResultDto $mailLogs,
        public ?BackupsResultDto $backups,
    ) {
    }
}
