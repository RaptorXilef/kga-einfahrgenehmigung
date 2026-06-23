<?php

declare(strict_types=1);

namespace App\Core\Entity;

/**
 * TODO
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class MailJob
{
    public function __construct(
        public string $id,
        public string $recipient,
        public string $subject,
        public string $template,
        public array $data,
        public int $attempts,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
