<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\ViewDashboard;

use App\Modules\Permit\Application\UseCases\GetDashboardPermits\DashboardPermitDto;
use App\Modules\Permit\Application\UseCases\GetDashboardStats\DashboardStatsDto;
use App\Modules\Permit\Application\UseCases\GetFinanceList\FinancePermitDto;
use App\Modules\Permit\Application\UseCases\GetGeneratorToolsData\GeneratorToolsViewDto;
use App\Modules\System\Application\UseCases\GetAuditLogsData\AuditLogViewDto;
use App\Modules\System\Application\UseCases\GetBackupsData\BackupsResultDto;
use App\Modules\System\Application\UseCases\GetMailLogsData\MailLogsResultDto;

/**
 * Der allumfassende Read-Model-Container für das Admin-Dashboard.
 * Enthält keinerlei Logik, nur fertig aufbereitete Strings, Booleans und Sub-DTOs.
 */
final readonly class DashboardViewDto
{
    /**
     * @param array<string, DashboardTabStateDto> $tabStates
     * @param CollectiveTransferViewDto[] $collectiveTransfers
     * @param DashboardPermitDto[] $activePermitsDto
     * @param DashboardPermitDto[] $futurePermitsDto
     * @param DashboardPermitDto[] $expiredPermitsDto
     * @param DashboardPermitDto[] $cancelledPermitsDto
     * @param FinancePermitDto[] $financePermitsDto
     * @param AuditLogViewDto[] $auditLogs
     * @param array<int, array{value: string, label: string, selectedAttr: string}> $auditFilterOptions
     * @param CronJobViewDto[] $cronJobs
     */
    public function __construct(
        public DashboardPermissionsDto $permissions,
        public array $tabStates,
        public ControlBarViewDto $controlBar,
        public array $collectiveTransfers,
        public array $activePermitsDto,
        public array $futurePermitsDto,
        public array $expiredPermitsDto,
        public array $cancelledPermitsDto,
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
        public string $paginationHtmlAudit,
        public int $financeTableColspan,
        public string $focus,
        public bool $showBankWizard,
        public BankImportWizardViewDto $bankWizard,
        public int $minArchiveYear,
        public string $expiredLoadArchiveUrl,
        public ?DashboardStatsDto $stats,
        public ?GeneratorToolsViewDto $generatorTools,
        public ?MailLogsResultDto $mailLogs,
        public ?BackupsResultDto $backups,
        public ?array $vouchers,
        public ?array $voucherArchive,
        public array $auditLogs,
        public int $auditTotal,
        public string $auditFilter,
        public array $auditFilterOptions,
        public array $unreadReleaseNotes,
        public string $bankImportMode,
        public string $cronSecret,
        public array $cronJobs,
    ) {
    }
}
