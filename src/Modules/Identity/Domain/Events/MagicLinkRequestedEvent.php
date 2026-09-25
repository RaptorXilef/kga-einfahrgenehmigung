<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Events;

use App\SharedKernel\Domain\Event\DomainEventInterface;
use DateTimeImmutable;
use Override;

/**
 * Event: Wird geworfen, wenn ein Nutzer einen Login-Link für seine Historie anfordert.
 */
final readonly class MagicLinkRequestedEvent implements DomainEventInterface
{
    public function __construct(
        public string $email,
        public string $token,
        public string $code,
        public DateTimeImmutable $occurredOn,
    ) {
    }

    #[Override]
    public function getOccurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
