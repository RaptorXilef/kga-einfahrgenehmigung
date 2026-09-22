<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use App\SharedKernel\Domain\ValueObject\IpAddress;
use DateTimeImmutable;

final readonly class LoginAttempt
{
    public function __construct(
        public IpAddress $ipAddress,
        public int $attempts,
        public DateTimeImmutable $lastAttempt,
    ) {
    }
}
