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
use App\Contracts\System\ImageStorageInterface;
use App\Core\Service\ReleaseNotesService;
use App\Modules\Identity\Application\Services\AuthService;
use App\Modules\Identity\Domain\RoleRepositoryInterface;
use App\Modules\Identity\Domain\UserRepositoryInterface;
use App\Modules\Permit\Application\UseCases\GetDashboardPermits\GetDashboardPermitsHandler;
use App\Modules\Permit\Application\UseCases\GetDashboardPermits\GetDashboardPermitsQuery;
use App\Modules\Permit\Application\UseCases\GetDashboardStats\GetDashboardStatsHandler;
use App\Modules\Permit\Application\UseCases\GetDashboardStats\GetDashboardStatsQuery;
use App\Modules\Permit\Application\UseCases\GetFinanceList\GetFinanceListHandler;
use App\Modules\Permit\Application\UseCases\GetFinanceList\GetFinanceListQuery;
use App\Modules\Voucher\Application\UseCases\GetVoucherArchive\GetVoucherArchiveHandler;
use App\Modules\Voucher\Application\UseCases\GetVoucherArchive\GetVoucherArchiveQuery;
use App\Modules\Voucher\Application\UseCases\GetVoucherList\GetVoucherListHandler;
use App\Modules\Voucher\Application\UseCases\GetVoucherList\GetVoucherListQuery;

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
        private ConfigInterface $config,
        private RoleRepositoryInterface $roleRepository,
        private ImageStorageInterface $imageStorage,
        private MailLogInterface $mailLog,
        private ReleaseNotesService $releaseNotesService,
        private SessionManager $sessionManager,
        private TemplateRenderer $renderer,
        private UserRepositoryInterface $userRepository,
        private GetVoucherListHandler $getVoucherListHandler,
        private GetVoucherArchiveHandler $getVoucherArchiveHandler,
        private GetFinanceListHandler $financeListHandler,
        private GetDashboardStatsHandler $statsHandler,
        private GetDashboardPermitsHandler $getDashboardPermitsHandler, // <-- NEUES CQRS READ-MODEL
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        $paginationCfg = $this->config->get('pagination', []);
        $dto = DashboardViewRequest::fromRequest($request->get, $this->sessionManager->getAdminFilters(), $paginationCfg);

        if ($dto->resetFilters) {
            $this->sessionManager->clearAdminFilters();
        }

        $filterStartYear = (int) \date('Y', \strtotime($dto->start));
        $requestedDepth = (int) ($request->get['archive_depth'] ?? $filterStartYear);
        $minArchiveYear = \min($filterStartYear, $requestedDepth);
        $focus = $request->get['focus'] ?? 'tab-active';

        // --- 1. DAS NEUE CQRS READ-MODEL FÜR ALLE TABS ---
        $permitsResult = $this->getDashboardPermitsHandler->handle(new GetDashboardPermitsQuery(
            filterStart: $dto->start,
            filterEnd: $dto->end,
            filterType: $dto->type,
            searchQuery: $dto->query,
            minArchiveYear: $minArchiveYear,
            activeTab: $focus,
            page: $dto->page,
            limit: $dto->limit,
        ));

        // --- 2. CQRS FINANCE & STATS SLICES ---
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

        // --- 3. Release Notes Logik ---
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
            'activePermitsDto' => $permitsResult->activePermitsDto,
            'futurePermitsDto' => $permitsResult->futurePermitsDto,
            'expiredPermitsDto' => $permitsResult->expiredPermitsDto,
            'cancelledPermitsDto' => $permitsResult->cancelledPermitsDto,
            'financePermitsDto' => $financePermitsDto,
            'totalActive' => $permitsResult->countActive,
            'totalFuture' => $permitsResult->countFuture,
            'totalExpired' => $permitsResult->countExpired,
            'totalCancelled' => $permitsResult->countCancelled,
            'totalUnpaid' => \count($financePermitsDto),
            'statsDto' => $statsDto,
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
