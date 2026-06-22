<?php

declare(strict_types=1);

namespace App\Core\Entity;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class MagicLink
{
    public function __construct(
        public string $token,
        public string $email,
        public string $code,
        public \DateTimeImmutable $expires,
    ) {
    }

    public function isExpired(\DateTimeImmutable $now): bool
    {
        return $this->expires < $now;
    }
}
