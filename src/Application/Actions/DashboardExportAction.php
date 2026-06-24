<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\ExportRequest;
use App\Application\Http\ServerRequest;
use App\Application\Response\EmptyResponse;
use App\Application\Response\FileDownloadResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Application\RequiresPermissionInterface;
use App\Contracts\Application\ViewActionInterface;
use App\Core\Service\ExportService;
use App\Core\Service\PermitFilterService;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class DashboardExportAction implements ViewActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private ExportService $exportService,
        private PermitFilterService $filterService,
        private SessionManager $sessionManager,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'finance.export.execute';
    }

    public function execute(ServerRequest $request): mixed
    {
        $sessionFilters = $this->sessionManager->getAdminFilters();
        $dto            = ExportRequest::fromArray($request->get, $sessionFilters);

        $start = $dto->start;
        $end   = $dto->end;
        $type  = $sessionFilters['type'] ?? 'all';
        $query = $sessionFilters['q'] ?? '';

        $filtered = $this->filterService->getFilteredPermits($start, $end, $type, $query);
        $filename = $this->exportService->generateFilename($dto->format, $start, $end);

        if ($dto->format === 'json') {
            return new FileDownloadResponse($this->exportService->generateJson($filtered), $filename, 'application/json');
        }

        if ($dto->format === 'csv') {
            return new FileDownloadResponse($this->exportService->generateCsv($filtered), $filename, 'text/csv; charset=utf-8');
        }

        return new EmptyResponse(400);
    }
}
