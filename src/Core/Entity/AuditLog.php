<?php

declare(strict_types=1);

namespace App\Core\Entity;

final readonly class AuditLog
{
    public function __construct(
        public string $id,
        public string $userId,
        public string $username,
        public string $action,
        public string $details,
        public string $ipAddress,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
