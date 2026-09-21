<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\UseCases\AnalyzeBankImport;

use App\SharedKernel\Application\Query\QueryInterface;

final readonly class AnalyzeBankImportQuery implements QueryInterface
{
    public function __construct(
        public string $tempFilePath,
    ) {
    }
}
