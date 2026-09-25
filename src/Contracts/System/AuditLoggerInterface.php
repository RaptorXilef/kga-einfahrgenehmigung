<?php

declare(strict_types=1);

namespace App\Contracts\System;

/**
 * Port für das revisionssichere Audit-Logging über alle Bounded Contexts hinweg.
 */
interface AuditLoggerInterface
{
    public function log(string $action, string $details): void;
}
