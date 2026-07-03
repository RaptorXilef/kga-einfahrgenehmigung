<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\DTO\DashboardViewRequest;
use App\Application\Http\ServerRequest;
use App\Application\Session\SessionManager;
use App\Application\View\TemplateRenderer;
use App\Contracts\Application\ViewActionInterface;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Mail\MailLogInterface;
use App\Contracts\Storage\AuditLogRepositoryInterface;
use App\Contracts\Storage\BackupServiceInterface;
use App\Contracts\Storage\CancelledPermitRepositoryInterface;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Contracts\Storage\StorageInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Contracts\Storage\VoucherRepositoryInterface;
use App\Core\Service\AuthService;
use App\Core\Service\PermitFilterService;
use App\Core\Service\PermitService;
use App\Core\Service\ReportingService;
use App\Core\Service\VoucherService;
use App\Infrastructure\Storage\ImageStorageService;

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
        private ImageStorageService $imageStorage,
        private MailLogInterface $mailLog,
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

        $allPermits = $this->storage->getAll();
        $filtered   = $this->filterService->getFilteredPermits($dto->start, $dto->end, $dto->type, $dto->query);

        $vouchers          = $this->voucherRepository->loadAll();
        $voucherValidities = [];
        foreach ($vouchers as $code => $v) {
            $voucherValidities[$code] = $this->voucherService->isValid($v);
        }

        $permitGroups  = $this->reportingService->groupPermits($filtered);
        $overdueLevels = [];
        foreach ($permitGroups['unpaid'] ?? [] as $permit) {
            $overdueLevels[$permit->code] = $this->permitService->getOverdueLevel($permit);
        }

        $cancelledPermits = $this->cancelledRepository->loadAll();

        // --- Audit Log Daten abrufen ---
        $auditPage   = \max(1, (int) ($request->get['audit_page'] ?? 1));
        $auditFilter = (string) ($request->get['audit_filter'] ?? '');
        $auditData   = $this->auditLogRepository->getPaginated($auditPage, 50, $auditFilter);

        $this->renderer->render('admin_dashboard', [
            'allowedLimits'     => $paginationCfg['allowed_limits'] ?? [10, 25, 50, 100, 250],
            'allPermits'        => $allPermits,
            'auditFilter'       => $auditFilter,
            'auditLogs'         => $auditData['items'],
            'auditPage'         => $auditPage,
            'auditTotal'        => $auditData['total'],
            'auth'              => $this->auth,
            'backups'           => $this->backupService->listBackups(),
            'cancelledPermits'  => $cancelledPermits,
            'currentPage'       => $dto->page,
            'filterEnd'         => $dto->end,
            'filterStart'       => $dto->start,
            'filterType'        => $dto->type,
            'groupRepository'   => $this->groupRepository,
            'imageStorage'      => $this->imageStorage,
            'itemsPerPage'      => $dto->limit,
            'mailLogs'          => $this->mailLog->loadLogs(),
            'overdueLevels'     => $overdueLevels,
            'periodStats'       => $this->reportingService->calculateDetailedStats($filtered),
            'permitGroups'      => $permitGroups,
            'structure'         => $this->config->get('structure', []),
            'userRepository'    => $this->userRepository,
            'voucherArchive'    => $this->voucherRepository->loadArchive(),
            'vouchers'          => $vouchers,
            'voucherValidities' => $voucherValidities,
            'yearlyStats'       => $this->reportingService->calculateYearlyStats($allPermits),
        ]);

        return null;
    }
}
