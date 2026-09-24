<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\UseCases\ProcessBankImport;

/**
 * Ein Transport-DTO für den Bank-Import Batch-Job.
 */
final readonly class ProcessBankImportCommand
{
    public function __construct(
        public string $tempFile,
        public int $idColumn,
        public int $amountColumn,
        public int $dateColumn,
    ) {
    }
}
