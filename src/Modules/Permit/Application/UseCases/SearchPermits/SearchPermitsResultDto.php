<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\SearchPermits;

/**
 * Stark typisiertes Read-Model-Ergebnis für die asynchrone Genehmigungssuche (/api/search_permits).
 */
final readonly class SearchPermitsResultDto
{
    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function __construct(
        public array $items,
        public int $total,
    ) {
    }
}
