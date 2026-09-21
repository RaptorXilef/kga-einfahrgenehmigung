<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\DTO\DashboardViewRequest;
use App\Application\Http\ServerRequest;
use App\Application\Response\HtmlResponse;
use App\Application\Session\SessionManager;
use App\Application\View\TemplateRenderer;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Mail\MailLogInterface;
use App\Contracts\Storage\AuditLogRepositoryInterface;
use App\Contracts\Storage\BackupServiceInterface;
use App\Contracts\Storage\CancelledPermitRepositoryInterface;
use App\Contracts\Storage\PermitArchiveRepositoryInterface;
use App\Contracts\Storage\RoleRepositoryInterface;
use App\Contracts\Storage\StorageInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Contracts\System\ImageStorageInterface;
use App\Core\Service\AuthService;
use App\Core\Service\PermitFilterService;
use App\Core\Service\PermitViewMapper;
use App\Core\Service\ReleaseNotesService;
use App\Modules\Permit\Application\UseCases\GetDashboardStats\GetDashboardStatsHandler;
use App\Modules\Permit\Application\UseCases\GetDashboardStats\GetDashboardStatsQuery;
use App\Modules\Permit\Application\UseCases\GetFinanceList\GetFinanceListHandler;
use App\Modules\Permit\Application\UseCases\GetFinanceList\GetFinanceListQuery;
use App\Modules\Voucher\Application\UseCases\GetVoucherArchive\GetVoucherArchiveHandler;
use App\Modules\Voucher\Application\UseCases\GetVoucherArchive\GetVoucherArchiveQuery;
use App\Modules\Voucher\Application\UseCases\GetVoucherList\GetVoucherListHandler;
use App\Modules\Voucher\Application\UseCases\GetVoucherList\GetVoucherListQuery;
use DateTimeImmutable;

/**
 * Rendert das zentrale Admin-Dashboard.
 */
#[Route('GET', '/admin')]
#[RequiresAuth]
final readonly class DashboardRenderAction implements ViewActionInterface
{
    public function __construct(
        private AuditLogRepositoryInterface $auditLogRepository,
        private AuthService $auth,
        private BackupServiceInterface $backupService,
        private CancelledPermitRepositoryInterface $cancelledRepository,
        private ConfigInterface $config,
        private RoleRepositoryInterface $roleRepository,
        private ImageStorageInterface $imageStorage,
        private MailLogInterface $mailLog,
        private PermitArchiveRepositoryInterface $archiveRepository,
        private PermitFilterService $filterService,
        private ReleaseNotesService $releaseNotesService,
        private SessionManager $sessionManager,
        private StorageInterface $storage,
        private TemplateRenderer $renderer,
        private UserRepositoryInterface $userRepository,
        private GetVoucherListHandler $getVoucherListHandler,
        private GetVoucherArchiveHandler $getVoucherArchiveHandler,
        private PermitViewMapper $permitMapper,
        private GetFinanceListHandler $financeListHandler,
        private GetDashboardStatsHandler $statsHandler,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        $paginationCfg = $this->config->get('pagination', []);
        $dto = DashboardViewRequest::fromRequest($request->get, $this->sessionManager->getAdminFilters(), $paginationCfg);

        if ($dto->resetFilters) {
            $this->sessionManager->clearAdminFilters();
        }

        // 1. Aktive & Archiv Daten holen (nur für die 3 Legacy-Tabs: Active, Future, Expired)
        $allActivePermits = $this->storage->getAll();
        $filteredActive = $this->filterService->getFilteredPermits($dto->start, $dto->end, $dto->type, $dto->query);

        // 2. Archiv laden (Lazy-Loading)
        $filterStartYear = (int) \date('Y', \strtotime($dto->start));
        $requestedDepth = (int) ($request->get['archive_depth'] ?? $filterStartYear);
        $minArchiveYear = \min($filterStartYear, $requestedDepth);

        $archivedPermits = $this->archiveRepository->getArchivedPermits($minArchiveYear);

        // 3. Datenbestände kombinieren
        $allHistoricalAndActive = $allActivePermits;
        $filteredHistoricalAndActive = $filteredActive;
        $queryLower = \strtolower(\trim($dto->query));

        foreach ($archivedPermits as $p) {
            $allHistoricalAndActive[] = $p;
            $pDate = $p->getCreatedAt()->format('Y-m-d');

            if ($pDate < $dto->start || $pDate > $dto->end) {
                continue;
            }
            if ($dto->type !== 'all' && (!(($this->config->get('permit_templates')[$p->template_key->value]['type'] ?? 'standard') === $dto->type))) {
                continue;
            }

            // Berücksichtige auch das Suchfeld für Archiv-Einträge!
            if ($queryLower !== '' && !$p->matchesSearch($queryLower)) {
                continue;
            }
            $filteredHistoricalAndActive[] = $p;
        }

        // Manuelles Filtern für die ersten 3 Tabs (bis diese auf PDO umgestellt sind)
        $now = new DateTimeImmutable('today');
        $activeGroups = ['active' => [], 'future' => [], 'expired' => []];
        foreach ($filteredHistoricalAndActive as $permit) {
            if ($permit->isExpired($now)) {
                $activeGroups['expired'][] = $permit;
            } elseif ($permit->isFuture($now)) {
                $activeGroups['future'][] = $permit;
            } else {
                $activeGroups['active'][] = $permit;
            }
        }

        $requirePayment = (bool) $this->config->get('require_payment_for_validity', false);
        $activePermitsDto = \array_map(fn ($p) => $this->permitMapper->mapToDashboardDto($p, $now, $requirePayment), $activeGroups['active']);
        $futurePermitsDto = \array_map(fn ($p) => $this->permitMapper->mapToDashboardDto($p, $now, $requirePayment), $activeGroups['future']);
        $expiredPermitsDto = \array_map(fn ($p) => $this->permitMapper->mapToDashboardDto($p, $now, $requirePayment), $activeGroups['expired']);

        $cancelledPermits = $this->cancelledRepository->loadAll();
        $cancelledPermitsDto = \array_map(fn ($p) => $this->permitMapper->mapToDashboardDto($p, $now, $requirePayment), $cancelledPermits);

        // --- CQRS FINANCE & STATS SLICES ---
        $financePermitsDto = $this->financeListHandler->handle(new GetFinanceListQuery());
        $statsDto = $this->statsHandler->handle(new GetDashboardStatsQuery($dto->start, $dto->end, $dto->type, $dto->query, $minArchiveYear));

        $vouchers = $this->getVoucherListHandler->handle(new GetVoucherListQuery());
        $voucherArchive = $this->getVoucherArchiveHandler->handle(new GetVoucherArchiveQuery());

        // Einheitlicher DTO-Page Parameter für die Datenbank-Abfrage
        $auditFilter = (string) ($request->get['audit_filter'] ?? '');
        $auditData = $this->auditLogRepository->getPaginated($dto->page, $dto->limit, $auditFilter);

        // Formulardaten (bei Fehlern) laden und Session leeren
        $formData = $this->sessionManager->getFormData() ?? [];
        $this->sessionManager->clearFormData();

        // --- Release Notes Logik ---
        $unreadReleaseNotes = [];
        $allReleaseNotes = $this->releaseNotesService->getAllNotes();
        $userId = $this->auth->getUserId();

        // Virtuelle Accounts (Backdoor) sehen den Dialog nicht dauerhaft
        if (!\str_starts_with($userId, 'sys_')) {
            $user = $this->userRepository->loadAll()[$userId] ?? null;
            if ($user) {
                $unreadReleaseNotes = $this->releaseNotesService->getUnreadNotes($user->lastSeenChangelog);
            }
        }

        // View rendern
        $html = $this->renderer->render('admin/dashboard', [
            'allowedLimits' => $paginationCfg['allowed_limits'] ?? [10, 25, 50, 100, 250],
            'allReleaseNotes' => $allReleaseNotes,
            'auditFilter' => $auditFilter,
            'auditLogs' => $auditData['items'],
            'auditTotal' => $auditData['total'],
            'auth' => $this->auth,
            'backups' => $this->auth->hasPermission('system.backup.manage') ? $this->backupService->listBackups() : [],
            'activePermitsDto' => $activePermitsDto,
            'futurePermitsDto' => $futurePermitsDto,
            'expiredPermitsDto' => $expiredPermitsDto,
            'cancelledPermitsDto' => $cancelledPermitsDto,
            'financePermitsDto' => $financePermitsDto, // <-- NEU
            'totalActive' => \count($activePermitsDto),
            'totalFuture' => \count($futurePermitsDto),
            'totalExpired' => \count($expiredPermitsDto),
            'totalCancelled' => \count($cancelledPermitsDto),
            'totalUnpaid' => \count($financePermitsDto), // <-- NEU
            'statsDto' => $statsDto, // <-- NEU
            'currentPage' => $dto->page,
            'filterEnd' => $dto->end,
            'filterQuery' => $dto->query,
            'filterStart' => $dto->start,
            'filterType' => $dto->type,
            'formData' => $formData,
            'roleRepository' => $this->roleRepository,
            'imageStorage' => $this->imageStorage,
            'itemsPerPage' => $dto->limit,
            'mailLogs' => $this->mailLog->loadLogs(),
            'minArchiveYear' => $minArchiveYear,
            'structure' => $this->config->get('structure', []),
            'unreadReleaseNotes' => $unreadReleaseNotes,
            'userRepository' => $this->userRepository,
            'voucherArchive' => $voucherArchive,
            'vouchers' => $vouchers,
            'collectiveTransfers' => $this->sessionManager->getCollectiveTransfers(),
        ]);

        return new HtmlResponse($html);
    }
}
