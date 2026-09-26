<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\GetAuditLogsData;

use App\SharedKernel\Application\Query\QueryInterface;

/**
 * Query zum Abrufen einer paginierten und optional gefilterten Seite des Sicherheits-Audit-Logs.
 */
final readonly class GetAuditLogsDataQuery implements QueryInterface
{
    public function __construct(
        public int $page,
        public int $limit,
        public string $actionFilter = '',
    ) {
    }
}
