<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\SearchPermits;

use App\SharedKernel\Application\Query\QueryInterface;

final readonly class SearchPermitsQuery implements QueryInterface
{
    public function __construct(
        public string $query,
        public int $page,
        public int $limit,
        public string $tab,
        public string $templateType,
    ) {
    }
}
