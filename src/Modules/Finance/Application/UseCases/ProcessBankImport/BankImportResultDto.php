<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\UseCases\ProcessBankImport;

final readonly class BankImportResultDto
{
    public function __construct(
        public bool $success,
        public string $message,
        public int $successCount = 0,
        public int $skippedCount = 0,
        public int $errorCount = 0,
        public array $successDetails = [],
        public array $skippedDetails = [],
        public array $errorDetails = [],
        public array $collectiveTransfers = [],
    ) {
    }
}
