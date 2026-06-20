<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\ExportRequest;
use App\Application\Http\ServerRequest;
use App\Application\Response\EmptyResponse;
use App\Application\Response\FileDownloadResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Application\ViewActionInterface;
use App\Core\Service\ExportService;
use App\Core\Service\PermitFilterService;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class DashboardExportAction implements ViewActionInterface
{
    public function __construct(
        private ExportService $exportService,
        private PermitFilterService $filterService,
        private SessionManager $sessionManager,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        $dto            = ExportRequest::fromArray($request->get);
        $sessionFilters = $this->sessionManager->getAdminFilters();

        $start = $dto->start !== 'all' ? $dto->start : ($sessionFilters['start'] ?? \date('Y-01-01'));
        $end   = $dto->end !== 'all' ? $dto->end : ($sessionFilters['end'] ?? \date('Y-12-31'));
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
