<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\UseCases\ProcessBankImport;

use App\SharedKernel\Application\Command\CommandInterface;

/**
 * Ein Workflow-Command (ohne das strenge CommandInterface, da dieses Use-Case
 * zwingend ein DTO an die UI zurückgeben muss).
 */
final readonly class ProcessBankImportCommand implements CommandInterface
{
    public function __construct(
        public string $tempFile,
        public int $idColumn,
        public int $amountColumn,
        public int $dateColumn,
        public ProcessBankImportContext $context = new ProcessBankImportContext(),
    ) {
    }
}
