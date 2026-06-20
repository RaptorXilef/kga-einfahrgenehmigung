<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\ExportRequest;
use App\Application\Http\ServerRequest;
use App\Application\Response\EmptyResponse;
use App\Application\Response\FileDownloadResponse;
use App\Contracts\Application\ViewActionInterface;
use App\Core\Service\ExportService;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class DashboardExportAction implements ViewActionInterface
{
    public function __construct(
        private ExportService $exportService,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        $dto = ExportRequest::fromArray($request->get);

        $format   = $dto->format;
        $start    = $dto->start;
        $end      = $dto->end;
        $filtered = []; // Das heir später fixen!

        $filename = $this->exportService->generateFilename($format, $start, $end);

        if ($format === 'json') {
            return new FileDownloadResponse($this->exportService->generateJson($filtered), $filename, 'application/json');
        }
        if ($format === 'csv') {
            return new FileDownloadResponse($this->exportService->generateCsv($filtered), $filename, 'text/csv; charset=utf-8');
        }

        return new EmptyResponse(400);
    }
}
