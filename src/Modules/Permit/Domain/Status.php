<?php

declare(strict_types=1);

namespace App\Modules\Permit\Domain;

use DateTimeImmutable;

final readonly class Status
{
    public function __construct(
        public PermitStatus $current = PermitStatus::Offen,
        public bool $is_suspended = false,
        public ?string $suspension_reason = null,
        public ?DateTimeImmutable $last_reminder_at = null,
    ) {
    }
}
