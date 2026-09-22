<?php

declare(strict_types=1);

namespace App\Modules\System\Domain;

use App\SharedKernel\Domain\ValueObject\IpAddress;
use DateTimeImmutable;

final readonly class AuditLog
{
    public function __construct(
        public string $id,
        public string $userId,
        public string $username,
        public string $action,
        public string $details,
        public IpAddress $ipAddress,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
