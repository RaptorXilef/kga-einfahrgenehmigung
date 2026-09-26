<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\UseCases\ProcessBankImport;

use App\SharedKernel\Application\Command\CommandInterface;

/**
 * Ein Transport-DTO für den Bank-Import Batch-Job.
 */
final readonly class ProcessBankImportCommand implements CommandInterface
{
    public function __construct(
        public string $tempFile,
        public int $idColumn,
        public int $amountColumn,
        public int $dateColumn,
        public int $senderColumn = 11,
        public int $currencyColumn = 15,
    ) {
    }
}
