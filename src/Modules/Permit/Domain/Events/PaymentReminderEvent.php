<?php

declare(strict_types=1);

namespace App\Modules\Permit\Domain\Events;

use App\Modules\Permit\Domain\Permit;

/**
 * Event: Wird geworfen, wenn eine Zahlungserinnerung fällig ist.
 */
final readonly class PaymentReminderEvent
{
    public function __construct(
        public Permit $permit,
    ) {
    }
}
