<?php

declare(strict_types=1);

namespace App\Core\Event;

use App\Core\Entity\Permit;

final readonly class PermitCancelledEvent
{
    public function __construct(
        public Permit $permit,
    ) {
    }
}
