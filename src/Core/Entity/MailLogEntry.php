<?php

declare(strict_types=1);

namespace App\Core\Entity;

use App\Core\ValueObject\TemplateKey;

/**
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class MailLogEntry
{
    public function __construct(
        public string $id,
        public \DateTimeImmutable $timestamp,
        public string $recipient,
        public string $subject,
        public TemplateKey $template,
        public string $status,
        public array $data,
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->status === 'Erfolg';
    }
}
