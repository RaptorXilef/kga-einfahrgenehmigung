<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\GetAuditLogsData;

/**
 * Container-DTO für die paginierten Audit-Log-Einträge im Admin-Dashboard.
 */
final readonly class AuditLogsResultDto
{
    /**
     * @param AuditLogViewDto[] $items
     */
    public function __construct(
        public array $items,
        public int $total,
    ) {
    }
}
