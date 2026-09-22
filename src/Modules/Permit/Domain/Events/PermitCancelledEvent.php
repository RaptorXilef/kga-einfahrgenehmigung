<?php

declare(strict_types=1);

namespace App\Modules\Permit\Domain\Events;

use App\Modules\Permit\Domain\Permit;

/**
 * Event: Wird geworfen, wenn ein Pächter seinen Antrag storniert.
 */
final readonly class PermitCancelledEvent
{
    public function __construct(
        public Permit $permit,
    ) {
    }
}
