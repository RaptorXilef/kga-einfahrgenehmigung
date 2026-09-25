<?php

declare(strict_types=1);

namespace App\Modules\Permit\Domain\Events;

use App\Modules\Permit\Domain\Permit;
use App\SharedKernel\Domain\Event\DomainEventInterface;
use DateTimeImmutable;
use Override;

/**
 * Event: Wird geworfen, sobald eine neue Genehmigung erfolgreich erstellt wurde.
 */
final readonly class PermitCreatedEvent implements DomainEventInterface
{
    public function __construct(
        public Permit $permit,
        public string $shortCode,
        public DateTimeImmutable $occurredOn,
    ) {
    }

    #[Override]
    public function getOccurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
