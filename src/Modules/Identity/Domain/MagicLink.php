<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use App\SharedKernel\Domain\ValueObject\EmailAddress;
use DateTimeImmutable;

/**
 * Aggregatwurzel für einen temporären, passwortlosen Login (OTP / Magic Link).
 */
final class MagicLink
{
    public function __construct(
        public readonly string $token,
        public readonly EmailAddress $email,
        public readonly string $code,
        public readonly DateTimeImmutable $expiresAt,
    ) {
    }

    public function isExpired(DateTimeImmutable $now): bool
    {
        return $this->expiresAt < $now;
    }
}
