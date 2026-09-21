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
use App\Core\Service\PermitService;
use App\Core\Service\PermitViewMapper;
use App\Core\Service\ReleaseNotesService;
use App\Core\Service\ReportingService;
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
        private PermitService $permitService,
        private ReleaseNotesService $releaseNotesService,
        private ReportingService $reportingService,
        private SessionManager $sessionManager,
        private StorageInterface $storage,
        private TemplateRenderer $renderer,
        private UserRepositoryInterface $userRepository,
        private GetVoucherListHandler $getVoucherListHandler,
        private GetVoucherArchiveHandler $getVoucherArchiveHandler,
        private PermitViewMapper $permitMapper, // <-- NEU
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        $paginationCfg = $this->config->get('pagination', []);
        $dto = DashboardViewRequest::fromRequest($request->get, $this->sessionManager->getAdminFilters(), $paginationCfg);

        if ($dto->resetFilters) {
            $this->sessionManager->clearAdminFilters();
        }

        // 1. Aktive Daten holen
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

        // 4. Tab-Gruppierungen erstellen
        $permitGroups = $this->reportingService->groupPermits($filteredHistoricalAndActive);

        // --- MAP DTOs FÜR DIE VIEWS (Das macht die PHTMLs dumm und sicher) ---
        $now = new DateTimeImmutable('today');
        $requirePayment = (bool) $this->config->get('require_payment_for_validity', false);

        $activePermitsDto = \array_map(fn ($p) => $this->permitMapper->mapToDashboardDto($p, $now, $requirePayment), $permitGroups['active'] ?? []);
        $futurePermitsDto = \array_map(fn ($p) => $this->permitMapper->mapToDashboardDto($p, $now, $requirePayment), $permitGroups['future'] ?? []);
        $expiredPermitsDto = \array_map(fn ($p) => $this->permitMapper->mapToDashboardDto($p, $now, $requirePayment), $permitGroups['expired'] ?? []);

        // Zähler für die Tabs!
        $totalActive = \count($activePermitsDto);
        $totalFuture = \count($futurePermitsDto);
        $totalExpired = \count($expiredPermitsDto);

        // 5. Restliche Daten laden (Alles via CQRS!)
        // DIE MAGIE: 1 saubere Zeile, 0 Entities, rasend schnell
        $vouchers = $this->getVoucherListHandler->handle(new GetVoucherListQuery());
        $voucherArchive = $this->getVoucherArchiveHandler->handle(new GetVoucherArchiveQuery());

        $cancelledPermits = $this->cancelledRepository->loadAll();
        // Cancelled DTO Mapping
        $cancelledPermitsDto = \array_map(fn ($p) => $this->permitMapper->mapToDashboardDto($p, $now, $requirePayment), $cancelledPermits);
        $totalCancelled = \count($cancelledPermitsDto);

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

        // 6. View rendern
        $html = $this->renderer->render('admin/dashboard', [
            'allowedLimits' => $paginationCfg['allowed_limits'] ?? [10, 25, 50, 100, 250],
            'allPermits' => $allHistoricalAndActive,
            'allReleaseNotes' => $allReleaseNotes,
            'auditFilter' => $auditFilter,
            'auditLogs' => $auditData['items'],
            'auditTotal' => $auditData['total'],
            'auth' => $this->auth,
            'backups' => $this->auth->hasPermission('system.backup.manage') ? $this->backupService->listBackups() : [],
            // DTO Variablen übergeben:
            'activePermitsDto' => $activePermitsDto,
            'futurePermitsDto' => $futurePermitsDto,
            'expiredPermitsDto' => $expiredPermitsDto,
            'cancelledPermitsDto' => $cancelledPermitsDto,
            'totalActive' => $totalActive,
            'totalFuture' => $totalFuture,
            'totalExpired' => $totalExpired,
            'totalCancelled' => $totalCancelled,
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
            'periodStats' => $this->reportingService->calculateDetailedStats($filteredHistoricalAndActive),
            'permitGroups' => $permitGroups, // Bleibt für Finance/Stats Tab vorerst drin
            'structure' => $this->config->get('structure', []),
            'unreadReleaseNotes' => $unreadReleaseNotes,
            'userRepository' => $this->userRepository,
            'voucherArchive' => $voucherArchive,
            'vouchers' => $vouchers,
            'yearlyStats' => $this->reportingService->calculateYearlyStats($allHistoricalAndActive),
            'collectiveTransfers' => $this->sessionManager->getCollectiveTransfers(),
        ]);

        return new HtmlResponse($html);
    }
}
