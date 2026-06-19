<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\ViewRenderRequest;
use App\Application\Session\SessionManager;
use App\Application\View\TemplateRenderer;
use App\Contracts\Application\ViewActionInterface;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Mail\MailLogInterface;
use App\Contracts\Storage\BackupServiceInterface;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Contracts\Storage\StorageInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Contracts\Storage\VoucherRepositoryInterface;
use App\Core\Entity\Permit;
use App\Core\Service\AuthService;
use App\Core\Service\PermitService;
use App\Core\Service\ReportingService;
use App\Core\Service\VoucherService;

/**
 * Rendert das zentrale Admin-Dashboard.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class DashboardRenderAction implements ViewActionInterface
{
    public function __construct(
        private AuthService $auth,
        private BackupServiceInterface $backupService,
        private ConfigInterface $config,
        private GroupRepositoryInterface $groupRepository,
        private MailLogInterface $mailLog,
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

    public function execute(array $requestData): mixed
    {
        $get = $requestData['get'] ?? [];
        $dto = ViewRenderRequest::fromArray($get);

        if (isset($get['reset_filters'])) {
            $this->sessionManager->clearAdminFilters();
        }

        $sessionFilters = $this->sessionManager->getAdminFilters();
        $filterStart    = (string) ($sessionFilters['start'] ?? $get['start'] ?? \date('Y-01-01'));
        $filterEnd      = (string) ($sessionFilters['end'] ?? $get['end'] ?? \date('Y-12-31'));
        $filterType     = (string) ($sessionFilters['type'] ?? $get['type'] ?? 'all');
        $searchQuery    = \strtolower(\trim((string) ($sessionFilters['q'] ?? $get['q'] ?? '')));

        $paginationCfg  = $this->config->get('pagination', []);
        $allowedLimits  = $paginationCfg['allowed_limits'] ?? [10, 25, 50, 100];
        $defaultLimit   = (int) ($paginationCfg['default_limit'] ?? 25);
        $requestedLimit = (int) ($sessionFilters['limit'] ?? $get['limit'] ?? $defaultLimit);
        $itemsPerPage   = \in_array($requestedLimit, $allowedLimits, true) ? $requestedLimit : $defaultLimit;
        $currentPage    = \max(1, (int) ($get['page'] ?? 1));

        $allPermits      = $this->storage->getAll();
        $permitTemplates = $this->config->get('permit_templates', []);

        $filtered = \array_filter($allPermits, function (Permit $permit) use ($filterStart, $filterEnd, $filterType, $permitTemplates, $searchQuery): bool {
            $date = $permit->getCreatedAt()->format('Y-m-d');
            if ($date < $filterStart || $date > $filterEnd) {
                return false;
            }
            if ($filterType !== 'all') {
                $tplType = $permitTemplates[$permit->template_key]['type'] ?? 'standard';
                if ($tplType !== $filterType) {
                    return false;
                }
            }
            if ($searchQuery !== '') {
                $haystack = \strtolower($permit->code . ' ' . $permit->getOwnerName() . ' ' . $permit->getOwnerEmail() . ' ' . $permit->getPlotNumber() . ' ' . $permit->getLicensePlate() . ' ' . $permit->getPurpose());
                if (! \str_contains($haystack, $searchQuery)) {
                    return false;
                }
            }

            return true;
        });

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

        $this->renderer->render('admin_dashboard', [
            'allowedLimits'     => $allowedLimits,
            'allPermits'        => $allPermits,
            'auth'              => $this->auth,
            'backups'           => $this->backupService->listBackups(),
            'currentPage'       => $currentPage,
            'filterEnd'         => $filterEnd,
            'filterStart'       => $filterStart,
            'filterType'        => $filterType,
            'groupRepository'   => $this->groupRepository,
            'itemsPerPage'      => $itemsPerPage,
            'mailLogs'          => $this->mailLog->loadLogs(),
            'message'           => $dto->message,
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
