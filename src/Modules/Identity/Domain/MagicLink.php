<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use App\SharedKernel\Domain\ValueObject\EmailAddress;
use DateTimeImmutable;

/**
 * Aggregatwurzel für einen temporären, passwortlosen Login (OTP / Magic Link).
 */
final readonly class MagicLink
{
    public function __construct(
        public string $token,
        public EmailAddress $email,
        public string $code,
        public DateTimeImmutable $expiresAt,
    ) {
    }

    public function isExpired(DateTimeImmutable $now): bool
    {
        return $this->expiresAt < $now;
    }
}
