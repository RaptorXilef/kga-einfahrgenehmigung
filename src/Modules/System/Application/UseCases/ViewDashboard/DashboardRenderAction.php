<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\ViewDashboard;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\HtmlResponse;
use App\Application\Session\SessionManager;
use App\Application\View\PaginationViewDto;
use App\Application\View\TemplateRenderer;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Security\AuthorizationInterface;
use App\Contracts\System\AssetHelperInterface;
use App\Contracts\System\SystemInfoInterface;
use App\Contracts\Utils\ClockInterface;
use App\Modules\Permit\Application\UseCases\GetDashboardPermits\DashboardPermitsResultDto;
use App\Modules\Permit\Application\UseCases\GetDashboardPermits\GetDashboardPermitsHandler;
use App\Modules\Permit\Application\UseCases\GetDashboardPermits\GetDashboardPermitsQuery;
use App\Modules\Permit\Application\UseCases\GetDashboardStats\DashboardStatsDto;
use App\Modules\Permit\Application\UseCases\GetDashboardStats\GetDashboardStatsHandler;
use App\Modules\Permit\Application\UseCases\GetDashboardStats\GetDashboardStatsQuery;
use App\Modules\Permit\Application\UseCases\GetFinanceList\GetFinanceListHandler;
use App\Modules\Permit\Application\UseCases\GetFinanceList\GetFinanceListQuery;
use App\Modules\Permit\Application\UseCases\GetGeneratorToolsData\GetGeneratorToolsDataHandler;
use App\Modules\Permit\Application\UseCases\GetGeneratorToolsData\GetGeneratorToolsDataQuery;
use App\Modules\System\Application\UseCases\GetAuditLogsData\AuditLogsResultDto;
use App\Modules\System\Application\UseCases\GetAuditLogsData\GetAuditLogsDataHandler;
use App\Modules\System\Application\UseCases\GetAuditLogsData\GetAuditLogsDataQuery;
use App\Modules\System\Application\UseCases\GetBackupsData\GetBackupsDataHandler;
use App\Modules\System\Application\UseCases\GetBackupsData\GetBackupsDataQuery;
use App\Modules\System\Application\UseCases\GetMailLogsData\GetMailLogsDataHandler;
use App\Modules\System\Application\UseCases\GetMailLogsData\GetMailLogsDataQuery;
use App\Modules\System\Application\UseCases\GetMailLogsData\MailLogsResultDto;
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
final readonly class DashboardRenderAction implements ViewActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuthorizationInterface $auth,
        private ConfigInterface $config,
        private SystemInfoInterface $systemInfo,
        private SessionManager $sessionManager,
        private TemplateRenderer $renderer,
        private AssetHelperInterface $assetHelper,
        private GetVoucherListHandler $getVoucherListHandler,
        private GetVoucherArchiveHandler $getVoucherArchiveHandler,
        private GetFinanceListHandler $financeListHandler,
        private GetDashboardStatsHandler $statsHandler,
        private GetDashboardPermitsHandler $getDashboardPermitsHandler,
        private GetGeneratorToolsDataHandler $generatorToolsHandler,
        private GetMailLogsDataHandler $mailLogsHandler,
        private GetAuditLogsDataHandler $auditLogsHandler,
        private GetBackupsDataHandler $backupsHandler,
        private ClockInterface $clock,
    ) {
    }

    #[Override]
    public function getRequiredPermission(): string
    {
        return 'admin.access';
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
        $focus = (string) ($request->get['focus'] ?? 'tab-active');

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
            'formData' => $this->sessionManager->getFormData(),
        ]);

        $this->sessionManager->clearFormData();

        return new HtmlResponse($html);
    }

    private function buildDashboardViewDto(
        DashboardViewRequest $dto,
        string $focus,
        int $minArchiveYear,
        DashboardPermitsResultDto $permitsResult,
        array $financePermitsDto,
        ?DashboardStatsDto $statsDto,
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

        // Bank Wizard DTO vorab aufbauen (steuert ggf. den aktiven Tab)
        $bankImportMode = $this->config->getString('bank_import_mode', 'simple');
        $formData = $this->sessionManager->getFormData();
        $bankWizard = $this->buildBankWizardDto($formData, $bankImportMode);
        $showBankWizard = $bankWizard->hasHeaders;

        if ($showBankWizard) {
            $focus = 'tab-bank-import';
        }

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
            $limitOptions[] = new LimitOptionDto((int) $l, $dto->limit === (int) $l ? 'selected' : '');
        }

        $activeTypeValue = \in_array($dto->type, ['standard', 'permanent'], true) ? $dto->type : 'all';

        $controlBar = new ControlBarViewDto(
            startValue: $dto->start,
            endValue: $dto->end,
            startValueFormatted: $dtStart->format('d.m.Y'),
            endValueFormatted: $dtEnd->format('d.m.Y'),
            activeTypeValue: $activeTypeValue,
            typeSelectAll: $activeTypeValue === 'all' ? 'selected' : '',
            typeSelectStandard: $activeTypeValue === 'standard' ? 'selected' : '',
            typeSelectPermanent: $activeTypeValue === 'permanent' ? 'selected' : '',
            limitOptions: $limitOptions,
            searchValue: $dto->query,
            showResetButton: $this->sessionManager->getAdminFilters() !== [],
        );

        // Sammelüberweisungen für den Finance-Tab
        $collectiveTransfers = [];
        foreach ($this->sessionManager->getCollectiveTransfers() as $ct) {
            $typeLabel = ($ct['type'] ?? 'sammel') === 'kennzeichen' ? 'Kennzeichen-Match:' : 'Mehrere Codes:';
            $collectiveTransfers[] = new CollectiveTransferViewDto(
                id: (string) $ct['id'],
                date: (string) $ct['date'],
                amountFormatted: \number_format((float) $ct['amount'], 2, ',', '.'),
                purpose: (string) $ct['purpose'],
                typeLabel: $typeLabel,
                codes: (array) ($ct['codes'] ?? []),
            );
        }

        // Pagination HTML Generierung über das logikfreie PaginationViewDto
        $renderPagination = function (int $total, string $tabId, string $pageParam = 'page') use ($dto, $focus, $request): string {
            $page = $tabId === $focus ? (int) ($request->get[$pageParam] ?? $dto->page) : 1;
            $paginationDto = PaginationViewDto::fromParameters(
                page: $page,
                totalCount: $total,
                limit: $dto->limit,
                queryParams: $request->get,
                paginationParam: $pageParam,
                tabFocusId: $tabId,
            );

            return $this->renderer->render('partials/admin/pagination', [
                'paginationDto' => $paginationDto,
            ]);
        };

        // Paginierung für den Finance Tab
        $totalUnpaid = \count($financePermitsDto);
        $finPage = $focus === 'tab-finance' ? $dto->page : 1;
        $finTotalPages = \max(1, (int) \ceil($totalUnpaid / $dto->limit));
        $finPage = \min($finPage, $finTotalPages);
        $finOffset = ($finPage - 1) * $dto->limit;
        $slicedFinancePermits = \array_slice($financePermitsDto, $finOffset, $dto->limit);

        // Daten für die Dumb View Handler
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
        $auditResult = $permissions->canViewLogs
            ? $this->auditLogsHandler->handle(new GetAuditLogsDataQuery($auditPage, $dto->limit, $auditFilter))
            : new AuditLogsResultDto(items: [], total: 0);

        $auditFilterOptions = $this->buildAuditFilterOptions($auditFilter);
        $paginationHtmlAudit = $permissions->canViewLogs ? $renderPagination($auditResult->total, 'tab-audit-log', 'audit_page') : '';

        $unreadReleaseNotes = [];
        $userId = $this->auth->getUserId();
        if (!\str_starts_with($userId, 'sys_')) {
            $unreadReleaseNotes = $this->systemInfo->getUnreadReleaseNotes($this->auth->getLastSeenChangelog());
        }

        // Archiv URL
        $queryParams = $request->get;
        unset($queryParams['page'], $queryParams['audit_page']);
        $queryParams['archive_depth'] = $minArchiveYear - 1;
        $queryParams['focus'] = 'tab-expired';

        $cronSecret = $this->config->getString('cron_secret', '');
        $cronJobs = $this->buildCronJobsDto($cronSecret);

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
            bankWizard: $bankWizard,
            minArchiveYear: $minArchiveYear,
            expiredLoadArchiveUrl: '?' . \http_build_query($queryParams),
            stats: $statsDto,
            generatorTools: $generatorToolsDto,
            mailLogs: $mailLogsDto,
            backups: $backupsDto,
            vouchers: $vouchers,
            voucherArchive: $voucherArchive,
            auditLogs: $auditResult->items,
            auditTotal: $auditResult->total,
            auditFilter: $auditFilter,
            auditFilterOptions: $auditFilterOptions,
            unreadReleaseNotes: $unreadReleaseNotes,
            bankImportMode: $bankImportMode,
            cronSecret: $cronSecret,
            cronJobs: $cronJobs,
        );
    }

    /**
     * @param array<string, mixed> $formData
     */
    private function buildBankWizardDto(array $formData, string $bankImportMode): BankImportWizardViewDto
    {
        $rawWizard = \is_array($formData['bank_wizard'] ?? null) ? $formData['bank_wizard'] : [];
        $headers = \is_array($rawWizard['headers'] ?? null) ? $rawWizard['headers'] : [];
        $previewRow = \is_array($rawWizard['previewRow'] ?? null) ? $rawWizard['previewRow'] : [];
        $tempFile = (string) ($rawWizard['tempFile'] ?? '');

        $guessId = (int) ($rawWizard['guessId'] ?? 4);
        $guessAmount = (int) ($rawWizard['guessAmount'] ?? 14);
        $guessDate = (int) ($rawWizard['guessDate'] ?? 1);

        $idColumnOptions = [];
        $amountColumnOptions = [];
        $dateColumnOptions = [];

        foreach ($headers as $idx => $name) {
            $index = (int) $idx;
            $label = (string) $name;

            $idColumnOptions[] = [
                'index' => $index,
                'number' => $index + 1,
                'label' => $label,
                'selectedAttr' => $index === $guessId ? 'selected' : '',
            ];
            $amountColumnOptions[] = [
                'index' => $index,
                'number' => $index + 1,
                'label' => $label,
                'selectedAttr' => $index === $guessAmount ? 'selected' : '',
            ];
            $dateColumnOptions[] = [
                'index' => $index,
                'number' => $index + 1,
                'label' => $label,
                'selectedAttr' => $index === $guessDate ? 'selected' : '',
            ];
        }

        $previewJson = \json_encode(
            $previewRow,
            \JSON_UNESCAPED_UNICODE | \JSON_INVALID_UTF8_SUBSTITUTE | \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_APOS | \JSON_HEX_QUOT,
        ) ?: '[]';

        return new BankImportWizardViewDto(
            isSimpleMode: $bankImportMode === 'simple',
            hasHeaders: $headers !== [],
            tempFile: $tempFile,
            previewJson: $previewJson,
            idColumnOptions: $idColumnOptions,
            amountColumnOptions: $amountColumnOptions,
            dateColumnOptions: $dateColumnOptions,
        );
    }

    /**
     * @return array<int, array{value: string, label: string, selectedAttr: string}>
     */
    private function buildAuditFilterOptions(string $activeFilter): array
    {
        $rawOptions = [
            '' => 'Alle Aktionen',
            'LOGIN' => 'Erfolgreiche Logins (Admin)',
            'LOGOUT' => 'Abmeldungen (Admin)',
            'PERMIT_CREATE' => 'Genehmigung erstellt',
            'PERMIT_PAID' => 'Zahlung bestätigt',
            'PERMIT_SUSPENSION' => 'Sperre / Freigabe',
            'PERMIT_PRINT' => 'Genehmigung gedruckt',
            'BANK_IMPORT' => 'Autom. Bankabgleich',
            'VOUCHER_CREATE' => 'Gutschein erstellt',
            'VOUCHER_TOGGLE' => 'Gutschein umgeschaltet',
            'VOUCHER_DELETE' => 'Gutschein gelöscht',
            'USER_CREATE' => 'Benutzer angelegt',
            'USER_RENAME' => 'Benutzer umbenannt',
            'USER_CHANGE_ROLE' => 'Rechte geändert',
            'USER_DELETE' => 'Benutzer gelöscht',
            'ROLE_CREATE' => 'Rollenmatrix erstellt',
            'ROLE_UPDATE' => 'Rollenmatrix bearbeitet',
            'ROLE_DELETE' => 'Rollenmatrix gelöscht',
            'SYSTEM_BACKUP_CREATE' => 'Backup ausgelöst',
            'DATA_EXPORT' => 'Daten exportiert',
            'USER_HISTORY_LOGIN' => 'Pächter-Logins (Verlauf)',
            'USER_HISTORY_LOGOUT' => 'Pächter-Logouts',
            'USER_PERMIT_CANCEL' => 'Pächter-Stornos',
        ];

        $options = [];
        foreach ($rawOptions as $val => $label) {
            $options[] = [
                'value' => $val,
                'label' => $label,
                'selectedAttr' => $activeFilter === $val ? 'selected' : '',
            ];
        }

        return $options;
    }

    /**
     * @return CronJobViewDto[]
     */
    private function buildCronJobsDto(string $cronSecret): array
    {
        $baseUrl = \rtrim($this->config->getBaseUrl(), '/');

        return [
            new CronJobViewDto(
                label: 'Mail-Warteschlange abarbeiten',
                description: 'Versendet zwischengespeicherte Mails (falls Queue-Limit im Web erreicht wird).',
                url: $baseUrl . '/api/process_mail_queue?token=' . $cronSecret,
                interval: 'Alle 1 - 5 Minuten',
                iconUrl: $this->assetHelper->url('assets/img/icons/envelope.webp'),
            ),
            new CronJobViewDto(
                label: 'Zahlungserinnerungen versenden',
                description: 'Prüft auf überfällige Anträge und versendet Mahnungen.',
                url: $baseUrl . '/api/cron/reminders?token=' . $cronSecret,
                interval: 'Täglich (z.B. morgens um 08:00)',
                iconUrl: $this->assetHelper->url('assets/img/icons/bell.webp'),
            ),
            new CronJobViewDto(
                label: 'Archivierung & DSGVO',
                description: 'Verschiebt alte Genehmigungen ins Archiv und anonymisiert >10 Jahre alte Daten.',
                url: $baseUrl . '/api/cron/archive?token=' . $cronSecret,
                interval: 'Täglich (z.B. nachts um 02:00)',
                iconUrl: $this->assetHelper->url('assets/img/icons/archive.webp'),
            ),
            new CronJobViewDto(
                label: 'Auto-Backup & Rotation',
                description: 'Erstellt einen MySQL-Dump und löscht ältere Backups nach dem FIFO-Prinzip.',
                url: $baseUrl . '/api/cron/backup?token=' . $cronSecret,
                interval: 'Täglich (z.B. nachts um 03:00)',
                iconUrl: $this->assetHelper->url('assets/img/icons/package.webp'),
            ),
            new CronJobViewDto(
                label: 'Spam-Filter synchronisieren',
                description: 'Lädt die tagesaktuellen Trashmail-Domains über GitHub in den Cache herunter.',
                url: $baseUrl . '/api/cron/spam_sync?token=' . $cronSecret,
                interval: 'Einmal Wöchentlich',
                iconUrl: $this->assetHelper->url('assets/img/icons/shield.webp'),
            ),
        ];
    }
}
