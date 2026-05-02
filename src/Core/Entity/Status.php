<?php

declare(strict_types=1);

namespace App\Core\Entity;

final readonly class Status
{
    public function __construct(
        public string $current = 'wartend',      // Status
        public bool $isSuspended = false,        // Ist die Genehmigung gesperrt?
        public ?string $suspensionReason = null, // Warum?
    ) {
    }
}
