<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\ViewDashboard;

/**
 * 100% logikfreies View-DTO für die Darstellung eines Audit-Log Eintrags.
 */
final readonly class AuditLogViewDto
{
    public function __construct(
        public string $dateFormatted,
        public string $timeFormatted,
        public string $action,
        public string $details,
        public string $username,
        public string $userId,
        public string $ipAddress,
        public string $avatarUrl,
    ) {
    }
}
