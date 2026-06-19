<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\ExportRequest;
use App\Contracts\Application\ViewActionInterface;
use App\Core\Service\ExportService;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class DashboardExportAction implements ViewActionInterface
{
    public function __construct(
        private ExportService $exportService,
    ) {
    }

    public function execute(array $requestData): void
    {
        $dto = ExportRequest::fromArray($requestData['get'] ?? []);

        $format   = $dto->format;
        $start    = $dto->start;
        $end      = $dto->end;
        $filtered = $requestData['filteredPermits'] ?? [];

        $filename = $this->exportService->generateFilename($format, $start, $end);

        if ($format === 'json') {
            \header('Content-Type: application/json');
            \header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo $this->exportService->generateJson($filtered);
            exit;
        }

        if ($format === 'csv') {
            \header('Content-Type: text/csv; charset=utf-8');
            \header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo $this->exportService->generateCsv($filtered);
            exit;
        }

        exit('Unbekanntes Export-Format.');
    }
}
