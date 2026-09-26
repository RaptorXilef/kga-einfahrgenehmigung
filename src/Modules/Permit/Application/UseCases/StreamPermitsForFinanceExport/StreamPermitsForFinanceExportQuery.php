<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\StreamPermitsForFinanceExport;

use App\SharedKernel\Application\Query\QueryInterface;

/**
 * Query zum speicherschonenden Streamen von Genehmigungsdaten für den Buchhaltungs-Export.
 */
final readonly class StreamPermitsForFinanceExportQuery implements QueryInterface
{
    public function __construct(
        public string $start,
        public string $end,
        public string $type,
        public string $searchQuery,
    ) {
    }
}
