<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\ExportPermits;

use App\SharedKernel\Application\Query\QueryInterface;

final readonly class ExportPermitsQuery implements QueryInterface
{
    public function __construct(
        public string $state,
        public string $start,
        public string $end,
        public string $type,
        public string $searchQuery,
    ) {
    }
}
