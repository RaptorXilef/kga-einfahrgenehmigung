<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Events;

/**
 * Event: Wird geworfen, wenn ein Nutzer einen Login-Link für seine Historie anfordert.
 */
final readonly class MagicLinkRequestedEvent
{
    public function __construct(
        public string $email,
        public string $token,
        public string $code,
    ) {
    }
}
