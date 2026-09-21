<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\UseCases\ExportFinanceData;

final readonly class FinanceExportResultDto
{
    public function __construct(
        public string $content,
        public string $filename,
        public string $contentType,
    ) {
    }
}
