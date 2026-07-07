<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\Contracts\ViewActionInterface;
use App\Application\DTO\DashboardViewRequest;
use App\Application\Http\ServerRequest;
use App\Application\Session\SessionManager;
use App\Application\View\TemplateRenderer;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Mail\MailLogInterface;
use App\Contracts\Storage\AuditLogRepositoryInterface;
use App\Contracts\Storage\BackupServiceInterface;
use App\Contracts\Storage\CancelledPermitRepositoryInterface;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Contracts\Storage\PermitArchiveRepositoryInterface;
use App\Contracts\Storage\StorageInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Contracts\Storage\VoucherRepositoryInterface;
use App\Contracts\System\ImageStorageInterface;
use App\Core\Service\AuthService;
use App\Core\Service\PermitFilterService;
use App\Core\Service\PermitService;
use App\Core\Service\ReportingService;
use App\Core\Service\VoucherService;

/**
 * Rendert das zentrale Admin-Dashboard.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('render_dashboard')]
final readonly class DashboardRenderAction implements ViewActionInterface
{
    public function __construct(
        private AuditLogRepositoryInterface $auditLogRepository,
        private AuthService $auth,
        private BackupServiceInterface $backupService,
        private CancelledPermitRepositoryInterface $cancelledRepository,
        private ConfigInterface $config,
        private GroupRepositoryInterface $groupRepository,
        private ImageStorageInterface $imageStorage,
        private MailLogInterface $mailLog,
        private PermitArchiveRepositoryInterface $archiveRepository,
        private PermitFilterService $filterService,
        private PermitService $permitService,
        private ReportingService $reportingService,
        private SessionManager $sessionManager,
        private StorageInterface $storage,
        private TemplateRenderer $renderer,
        private UserRepositoryInterface $userRepository,
        private VoucherRepositoryInterface $voucherRepository,
        private VoucherService $voucherService,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        $paginationCfg = $this->config->get('pagination', []);
        $dto           = DashboardViewRequest::fromRequest(
            $request->get,
            $this->sessionManager->getAdminFilters(),
            $paginationCfg,
        );

        if ($dto->resetFilters) {
            $this->sessionManager->clearAdminFilters();
        }

        // 1. Aktive Daten holen
        $allActivePermits = $this->storage->getAll();
        $filteredActive   = $this->filterService->getFilteredPermits($dto->start, $dto->end, $dto->type, $dto->query);

        // 2. Archiv laden (Lazy-Loading)
        $filterStartYear = (int) \date('Y', \strtotime($dto->start));
        $requestedDepth  = (int) ($request->get['archive_depth'] ?? $filterStartYear);
        $minArchiveYear  = \min($filterStartYear, $requestedDepth);

        $archivedPermits = $this->archiveRepository->getArchivedPermits($minArchiveYear);

        // 3. Datenbestände kombinieren
        $allHistoricalAndActive      = $allActivePermits;
        $filteredHistoricalAndActive = $filteredActive;
        $queryLower                  = \strtolower(\trim($dto->query)); // Für die Text-Suche

        foreach ($archivedPermits as $p) {
            $allHistoricalAndActive[] = $p;

            $pDate = $p->getCreatedAt()->format('Y-m-d');
            if ($pDate >= $dto->start && $pDate <= $dto->end) {
                if ($dto->type === 'all' || (($this->config->get('permit_templates')[$p->template_key]['type'] ?? 'standard') === $dto->type)) {
                    // Berücksichtige auch das Suchfeld für Archiv-Einträge!
                    if ($queryLower === '' || $p->matchesSearch($queryLower)) {
                        $filteredHistoricalAndActive[] = $p;
                    }
                }
            }
        }

        // 4. Tab-Gruppierungen ERST JETZT aus den kombinierten Daten erstellen!
        $permitGroups = $this->reportingService->groupPermits($filteredHistoricalAndActive);

        $overdueLevels = [];
        foreach ($permitGroups['unpaid'] ?? [] as $permit) {
            $overdueLevels[$permit->code] = $this->permitService->getOverdueLevel($permit);
        }

        // 5. Restliche Daten laden
        $vouchers          = $this->voucherRepository->loadAll();
        $voucherValidities = [];
        foreach ($vouchers as $code => $v) {
            $voucherValidities[$code] = $this->voucherService->isValid($v);
        }

        $cancelledPermits = $this->cancelledRepository->loadAll();

        $auditPage   = \max(1, (int) ($request->get['audit_page'] ?? 1));
        $auditFilter = (string) ($request->get['audit_filter'] ?? '');
        $auditData   = $this->auditLogRepository->getPaginated($auditPage, 50, $auditFilter);

        // 6. View rendern
        $this->renderer->render('admin_dashboard', [
            'allowedLimits'     => $paginationCfg['allowed_limits'] ?? [10, 25, 50, 100, 250],
            'allPermits'        => $allHistoricalAndActive, // An Template übergeben
            'auditFilter'       => $auditFilter,
            'auditLogs'         => $auditData['items'],
            'auditPage'         => $auditPage,
            'auditTotal'        => $auditData['total'],
            'auth'              => $this->auth,
            'backups'           => $this->backupService->listBackups(),
            'cancelledPermits'  => $cancelledPermits,
            'currentPage'       => $dto->page,
            'filterEnd'         => $dto->end,
            'filterQuery'       => $dto->query,
            'filterStart'       => $dto->start,
            'filterType'        => $dto->type,
            'groupRepository'   => $this->groupRepository,
            'imageStorage'      => $this->imageStorage,
            'itemsPerPage'      => $dto->limit,
            'mailLogs'          => $this->mailLog->loadLogs(),
            'minArchiveYear'    => $minArchiveYear,
            'overdueLevels'     => $overdueLevels,
            'periodStats'       => $this->reportingService->calculateDetailedStats($filteredHistoricalAndActive),
            'permitGroups'      => $permitGroups, // <--- Beinhaltet jetzt die archivierten!
            'structure'         => $this->config->get('structure', []),
            'userRepository'    => $this->userRepository,
            'voucherArchive'    => $this->voucherRepository->loadArchive(),
            'vouchers'          => $vouchers,
            'voucherValidities' => $voucherValidities,
            'yearlyStats'       => $this->reportingService->calculateYearlyStats($allHistoricalAndActive),
        ]);

        return null;
    }
}
