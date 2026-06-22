<?php

declare(strict_types=1);

namespace App\Core\Entity;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class VerificationRequest
{
    public function __construct(
        public string $token,
        public \DateTimeImmutable $expiresAt,
        public array $data,
    ) {
    }

    public function isExpired(\DateTimeImmutable $now): bool
    {
        return $this->expiresAt < $now;
    }
}
