<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain\Event;

use DateTimeImmutable;
use Override;

/**
 * Event: Wird vom Finance-Modul geworfen, wenn ein Zahlungseingang via CSV
 * erfolgreich einem Vorgang zugeordnet wurde.
 */
final readonly class BankPaymentAssignedEvent implements DomainEventInterface
{
    public function __construct(
        public string $permitCode,
        public string $reason,
        public string $bookingDate,
        public DateTimeImmutable $occurredOn = new DateTimeImmutable(),
    ) {
    }

    #[Override]
    public function getOccurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
