<?php

declare(strict_types=1);

namespace App\Core\Entity;

/**
 * TODO
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class LoginAttempt
{
    public function __construct(
        public string $ipAddress,
        public int $attempts,
        public \DateTimeImmutable $lastAttempt,
    ) {
    }
}
