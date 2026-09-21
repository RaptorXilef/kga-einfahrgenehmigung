<?php

declare(strict_types=1);

namespace App\Core\Event;

use App\Modules\Permit\Domain\Permit;

final readonly class PermitCancelledEvent
{
    public function __construct(
        public Permit $permit,
    ) {
    }
}
