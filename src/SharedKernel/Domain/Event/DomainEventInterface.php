<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain\Event;

use DateTimeImmutable;

/**
 * Marker-Interface für alle Domain-Events.
 * Erlaubt die asynchrone und entkoppelte Kommunikation zwischen den Bounded Contexts.
 */
interface DomainEventInterface
{
    /**
     * Gibt den exakten Zeitpunkt zurück, an dem das Event aufgetreten ist.
     */
    public function getOccurredOn(): DateTimeImmutable;
}
