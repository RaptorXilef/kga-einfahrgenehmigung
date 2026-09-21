<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\UseCases\AnalyzeBankImport;

final readonly class BankImportAnalysisDto
{
    public function __construct(
        public array $headers,
        public array $previewRow,
    ) {
    }
}
