<?php

declare(strict_types=1);

namespace App\Modules\System\Domain;

/**
 * Reines Write-Repository für das Speichern von Sicherheits-Audit-Logs (CQRS).
 */
interface AuditLogRepositoryInterface
{
    public function save(AuditLog $log): void;
}
