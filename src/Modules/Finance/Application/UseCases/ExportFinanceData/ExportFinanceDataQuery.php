<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\UseCases\ExportFinanceData;

use App\SharedKernel\Application\Query\QueryInterface;

final readonly class ExportFinanceDataQuery implements QueryInterface
{
    public function __construct(
        public string $format,
        public string $start,
        public string $end,
        public string $type,
        public string $searchQuery,
    ) {
    }
}
