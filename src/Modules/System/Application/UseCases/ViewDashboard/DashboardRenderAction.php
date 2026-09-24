<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\ViewDashboard;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\ResponseInterface;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\HtmlResponse;
use App\Application\Session\SessionManager;
use App\Application\View\TemplateRenderer;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\System\ImageStorageInterface;
use App\Contracts\System\SystemInfoInterface;
use App\Contracts\Utils\ClockInterface;
use App\Modules\Identity\Application\Services\AuthService;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserRepositoryInterface;
use App\Modules\Permit\Application\UseCases\GetDashboardPermits\GetDashboardPermitsHandler;
use App\Modules\Permit\Application\UseCases\GetDashboardPermits\GetDashboardPermitsQuery;
use App\Modules\Permit\Application\UseCases\GetDashboardStats\GetDashboardStatsHandler;
use App\Modules\Permit\Application\UseCases\GetDashboardStats\GetDashboardStatsQuery;
use App\Modules\Permit\Application\UseCases\GetFinanceList\GetFinanceListHandler;
use App\Modules\Permit\Application\UseCases\GetFinanceList\GetFinanceListQuery;
use App\Modules\Permit\Application\UseCases\GetGeneratorToolsData\GetGeneratorToolsDataHandler;
use App\Modules\Permit\Application\UseCases\GetGeneratorToolsData\GetGeneratorToolsDataQuery;
use App\Modules\System\Application\UseCases\GetBackupsData\GetBackupsDataHandler;
use App\Modules\System\Application\UseCases\GetBackupsData\GetBackupsDataQuery;
use App\Modules\System\Application\UseCases\GetMailLogsData\GetMailLogsDataHandler;
use App\Modules\System\Application\UseCases\GetMailLogsData\GetMailLogsDataQuery;
use App\Modules\System\Application\UseCases\GetMailLogsData\MailLogsResultDto;
use App\Modules\System\Domain\AuditLogRepositoryInterface;
use App\Modules\Voucher\Application\UseCases\GetVoucherArchive\GetVoucherArchiveHandler;
use App\Modules\Voucher\Application\UseCases\GetVoucherArchive\GetVoucherArchiveQuery;
use App\Modules\Voucher\Application\UseCases\GetVoucherList\GetVoucherListHandler;
use App\Modules\Voucher\Application\UseCases\GetVoucherList\GetVoucherListQuery;
use DateTimeImmutable;
use Override;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
#[Route('GET', '/admin')]
#[RequiresAuth]
final readonly class DashboardRenderAction implements ViewActionInterface
{
    public function __construct(
        private AuditLogRepositoryInterface $auditLogRepository,
        private AuthService $auth,
        private ConfigInterface $config,
        private SystemInfoInterface $systemInfo,
        private SessionManager $sessionManager,
        private TemplateRenderer $renderer,
        private UserRepositoryInterface $userRepository,
        private ImageStorageInterface $imageStorage,
        private GetVoucherListHandler $getVoucherListHandler,
        private GetVoucherArchiveHandler $getVoucherArchiveHandler,
        private GetFinanceListHandler $financeListHandler,
        private GetDashboardStatsHandler $statsHandler,
        private GetDashboardPermitsHandler $getDashboardPermitsHandler,
        private GetGeneratorToolsDataHandler $generatorToolsHandler,
        private GetMailLogsDataHandler $mailLogsHandler,
        private GetBackupsDataHandler $backupsHandler,
        private ClockInterface $clock,
    ) {
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        $paginationCfg = $this->config->getArray('pagination');
        $dto = DashboardViewRequest::fromRequest($request->get, $this->sessionManager->getAdminFilters(), $paginationCfg, $this->clock);

        if ($dto->resetFilters) {
            $this->sessionManager->clearAdminFilters();
        }

        $filterStartYear = (int) (new DateTimeImmutable($dto->start))->format('Y');
        $requestedDepth = (int) ($request->get['archive_depth'] ?? $filterStartYear);
        $minArchiveYear = \min($filterStartYear, $requestedDepth);
        $focus = $request->get['focus'] ?? 'tab-active';

        // 1. Core-Queries abfeuern
        $permitsResult = $this->getDashboardPermitsHandler->handle(new GetDashboardPermitsQuery(
            $dto->start,
            $dto->end,
            $dto->type,
            $dto->query,
            $minArchiveYear,
            $focus,
            $dto->page,
            $dto->limit,
        ));

        $financePermitsDto = $this->auth->hasPermission('finance.view') ? $this->financeListHandler->handle(new GetFinanceListQuery()) : [];
        $statsDto = $this->auth->hasPermission('stats.view') || $this->auth->hasPermission('stats.ranking')
            ? $this->statsHandler->handle(new GetDashboardStatsQuery($dto->start, $dto->end, $dto->type, $dto->query, $minArchiveYear))
            : null;

        // 2. View-Model für das Dashboard aufbauen
        $dashboardViewDto = $this->buildDashboardViewDto($dto, $focus, $minArchiveYear, $permitsResult, $financePermitsDto, $statsDto, $request);

        $html = $this->renderer->render('admin/dashboard', [
            'viewDto' => $dashboardViewDto,
            'formData' => $this->sessionManager->getFormData() ?? [],
        ]);

        $this->sessionManager->clearFormData();

        return new HtmlResponse($html);
    }

    private function buildDashboardViewDto(
        DashboardViewRequest $dto,
        string $focus,
        int $minArchiveYear,
        object $permitsResult,
        array $financePermitsDto,
        ?object $statsDto,
        ServerRequest $request,
    ): DashboardViewDto {

        $hasAnyPermitExport = $this->auth->hasPermission('permits.export.active')
            || $this->auth->hasPermission('permits.export.future')
            || $this->auth->hasPermission('permits.export.expired')
            || $this->auth->hasPermission('permits.export.active_future')
            || $this->auth->hasPermission('permits.export.all');

        // Berechtigungen flachziehen
        $permissions = new DashboardPermissionsDto(
            canViewPermits: $this->auth->hasPermission('permits.view'),
            canPrintPermits: $this->auth->hasPermission('permits.print'),
            canSuspendPermits: $this->auth->hasPermission('permits.suspend'),
            canViewFinance: $this->auth->hasPermission('finance.view'),
            canMarkPaid: $this->auth->hasPermission('finance.mark_paid'),
            canBankImport: $this->auth->hasPermission('finance.bank_import'),
            canExport: $this->auth->hasPermission('finance.export'),
            canCreatePermits: $this->auth->hasPermission('permits.create'),
            canManageVouchers: $this->auth->hasPermission('vouchers.create') || $this->auth->hasPermission('vouchers.suspend'),
            canViewVouchers: $this->auth->hasPermission('vouchers.view'),
            canViewStats: $this->auth->hasPermission('stats.view'),
            canViewCharts: $this->auth->hasPermission('stats.charts'),
            canViewRanking: $this->auth->hasPermission('stats.ranking'),
            canViewLogs: $this->auth->hasPermission('system.logs.view'),
            canManageSystem: $this->auth->hasPermission('system.maintenance.execute') || $this->auth->hasPermission('system.update.execute'),
            canExecuteMaintenance: $this->auth->hasPermission('system.maintenance.execute'),
            canExecuteUpdates: $this->auth->hasPermission('system.update.execute'),
            canManageBackups: $this->auth->hasPermission('system.backup.manage'),
            showPrivacyEmails: $this->auth->hasPermission('privacy.emails.view'),
            showPrivacyFinance: $this->auth->hasPermission('privacy.finance.view'),
            canExportPermitsActive: $this->auth->hasPermission('permits.export.active'),
            canExportPermitsFuture: $this->auth->hasPermission('permits.export.future'),
            canExportPermitsExpired: $this->auth->hasPermission('permits.export.expired'),
            canExportPermitsActiveFuture: $this->auth->hasPermission('permits.export.active_future'),
            canExportPermitsAll: $this->auth->hasPermission('permits.export.all'),
            hasAnyPermitExport: $hasAnyPermitExport,
        );

        // Tab States (Aktive CSS Klassen ohne if-Logik im PHTML)
        $tabIds = ['tab-active', 'tab-future', 'tab-expired', 'tab-cancelled', 'tab-ranking', 'tab-finance', 'tab-stats', 'tab-bank-import', 'tab-export', 'tab-tools', 'tab-vouchers', 'tab-logs', 'tab-audit-log', 'tab-system', 'tab-backup'];
        $tabStates = [];
        foreach ($tabIds as $tabId) {
            $isActive = $focus === $tabId;
            $tabStates[$tabId] = new DashboardTabStateDto(
                isActiveClass: $isActive ? 'is-active' : '',
                ariaSelected: $isActive ? 'true' : 'false',
                tabIndex: $isActive ? '0' : '-1',
                ariaHidden: $isActive ? 'false' : 'true',
            );
        }

        // Control Bar DTO
        $dtStart = new DateTimeImmutable($dto->start);
        $dtEnd = new DateTimeImmutable($dto->end);

        $limitOptions = [];
        $paginationCfg = $this->config->getArray('pagination');
        foreach ($paginationCfg['allowed_limits'] ?? [10, 25, 50, 100, 250] as $l) {
            $limitOptions[] = new LimitOptionDto($l, $dto->limit === $l ? 'selected' : '');
        }

        $controlBar = new ControlBarViewDto(
            startValue: $dto->start,
            endValue: $dto->end,
            startValueFormatted: $dtStart->format('d.m.Y'),
            endValueFormatted: $dtEnd->format('d.m.Y'),
            typeSelectAll: $dto->type === 'all' ? 'selected' : '',
            typeSelectStandard: $dto->type === 'standard' ? 'selected' : '',
            typeSelectPermanent: $dto->type === 'permanent' ? 'selected' : '',
            limitOptions: $limitOptions,
            searchValue: $dto->query,
            showResetButton: !empty($this->sessionManager->getAdminFilters()),
        );

        // Sammelüberweisungen für den Finance-Tab
        $collectiveTransfers = [];
        foreach ($this->sessionManager->getCollectiveTransfers() as $ct) {
            $typeLabel = ($ct['type'] ?? 'sammel') === 'kennzeichen' ? 'Kennzeichen-Match:' : 'Mehrere Codes:';
            $collectiveTransfers[] = new CollectiveTransferViewDto(
                id: $ct['id'],
                date: $ct['date'],
                amountFormatted: \number_format((float) $ct['amount'], 2, ',', '.'),
                purpose: $ct['purpose'],
                typeLabel: $typeLabel,
                codes: $ct['codes'] ?? [],
            );
        }

        // Pagination HTML Generierung
        $renderPagination = function (int $total, string $tabId, string $pageParam = 'page') use ($dto, $focus, $request): string {
            $limit = $dto->limit;
            $page = $tabId === $focus ? (int) ($request->get[$pageParam] ?? $dto->page) : 1;
            $totalPages = \max(1, (int) \ceil($total / $limit));
            $offset = ($page - 1) * $limit;

            return $this->renderer->render('partials/admin/pagination', [
                'page' => $page,
                'totalPages' => $totalPages,
                'totalCount' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'paginationParam' => $pageParam,
                'tabFocusId' => $tabId,
            ]);
        };

        // Paginierung für den Finance Tab
        $totalUnpaid = \count($financePermitsDto);
        $finPage = $focus === 'tab-finance' ? $dto->page : 1;
        $finTotalPages = \max(1, (int) \ceil($totalUnpaid / $dto->limit));
        $finPage = \min($finPage, $finTotalPages);
        $finOffset = ($finPage - 1) * $dto->limit;
        $slicedFinancePermits = \array_slice($financePermitsDto, $finOffset, $dto->limit);

        // Daten für die neuen Dumb View Handler
        $generatorToolsDto = $permissions->canCreatePermits || $permissions->canManageVouchers
            ? $this->generatorToolsHandler->handle(new GetGeneratorToolsDataQuery($this->auth))
            : null;

        $mailLogsDto = $permissions->canViewLogs
            ? $this->mailLogsHandler->handle(new GetMailLogsDataQuery($dto->page, $dto->limit))
            : null;

        $backupsDto = $permissions->canManageBackups
            ? $this->backupsHandler->handle(new GetBackupsDataQuery())
            : null;

        $paginationHtmlLogs = $mailLogsDto instanceof MailLogsResultDto ? $renderPagination($mailLogsDto->total, 'tab-logs') : '';

        $vouchers = $permissions->canViewVouchers ? $this->getVoucherListHandler->handle(new GetVoucherListQuery()) : null;
        $voucherArchive = $permissions->canViewVouchers ? $this->getVoucherArchiveHandler->handle(new GetVoucherArchiveQuery()) : null;

        $auditFilter = (string) ($request->get['audit_filter'] ?? '');
        $auditPage = (int) ($request->get['audit_page'] ?? 1);
        $auditData = $permissions->canViewLogs ? $this->auditLogRepository->getPaginated($auditPage, $dto->limit, $auditFilter) : ['items' => [], 'total' => 0];

        // Map AuditLog Entities to AuditLogViewDto to keep the view 100% logic-free
        $auditLogsDto = [];
        if ($permissions->canViewLogs) {
            foreach ($auditData['items'] as $log) {
                $auditLogsDto[] = new AuditLogViewDto(
                    dateFormatted: $log->createdAt->format('d.m.Y'),
                    timeFormatted: $log->createdAt->format('H:i:s'),
                    action: $log->action,
                    details: $log->details,
                    username: $log->username,
                    userId: $log->userId,
                    ipAddress: $log->ipAddress->value,
                    avatarUrl: $this->imageStorage->getImageUrl('user', $log->userId, 'user.webp'),
                );
            }
        }

        $paginationHtmlAudit = $permissions->canViewLogs ? $renderPagination($auditData['total'], 'tab-audit-log', 'audit_page') : '';

        $unreadReleaseNotes = [];
        $userId = $this->auth->getUserId();
        if (!\str_starts_with($userId, 'sys_')) {
            $user = $this->userRepository->findById($userId);
            if ($user instanceof User) {
                $unreadReleaseNotes = $this->systemInfo->getUnreadReleaseNotes($user->getLastSeenChangelog());
            }
        }

        // Archiv URL
        $queryParams = $request->get;
        unset($queryParams['page'], $queryParams['audit_page']);
        $queryParams['archive_depth'] = $minArchiveYear - 1;
        $queryParams['focus'] = 'tab-expired';

        // Bank Wizard
        $formData = $this->sessionManager->getFormData() ?? [];
        $showBankWizard = isset($formData['bank_wizard']['headers']) && !empty($formData['bank_wizard']['headers']);
        if ($showBankWizard) {
            $tabStates['tab-bank-import'] = new DashboardTabStateDto('is-active', 'true', '0', 'false');
            $focus = 'tab-bank-import';
        }

        $financeTableColspan = 5 + ($permissions->showPrivacyEmails ? 1 : 0) + ($permissions->showPrivacyFinance ? 1 : 0) + ($permissions->canMarkPaid ? 1 : 0);

        return new DashboardViewDto(
            permissions: $permissions,
            tabStates: $tabStates,
            controlBar: $controlBar,
            collectiveTransfers: $collectiveTransfers,
            activePermitsDto: $permitsResult->activePermitsDto,
            futurePermitsDto: $permitsResult->futurePermitsDto,
            expiredPermitsDto: $permitsResult->expiredPermitsDto,
            cancelledPermitsDto: $permitsResult->cancelledPermitsDto,
            financePermitsDto: $slicedFinancePermits,
            totalActive: $permitsResult->countActive,
            totalFuture: $permitsResult->countFuture,
            totalExpired: $permitsResult->countExpired,
            totalCancelled: $permitsResult->countCancelled,
            totalUnpaid: $totalUnpaid,
            paginationHtmlActive: $renderPagination($permitsResult->countActive, 'tab-active'),
            paginationHtmlFuture: $renderPagination($permitsResult->countFuture, 'tab-future'),
            paginationHtmlExpired: $renderPagination($permitsResult->countExpired, 'tab-expired'),
            paginationHtmlCancelled: $renderPagination($permitsResult->countCancelled, 'tab-cancelled'),
            paginationHtmlFinance: $renderPagination($totalUnpaid, 'tab-finance'),
            paginationHtmlLogs: $paginationHtmlLogs,
            paginationHtmlAudit: $paginationHtmlAudit,
            financeTableColspan: $financeTableColspan,
            focus: $focus,
            showBankWizard: $showBankWizard,
            minArchiveYear: $minArchiveYear,
            expiredLoadArchiveUrl: '?' . \http_build_query($queryParams),
            stats: $statsDto,
            generatorTools: $generatorToolsDto,
            mailLogs: $mailLogsDto,
            backups: $backupsDto,
            vouchers: $vouchers,
            voucherArchive: $voucherArchive,
            auditLogs: $auditLogsDto,
            auditTotal: $auditData['total'],
            auditFilter: $auditFilter,
            unreadReleaseNotes: $unreadReleaseNotes,
            bankImportMode: $this->config->getString('bank_import_mode', 'simple'),
            cronSecret: $this->config->getString('cron_secret', ''),
        );
    }
}
