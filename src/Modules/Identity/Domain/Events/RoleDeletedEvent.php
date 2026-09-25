<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Events;

use App\SharedKernel\Domain\Event\DomainEventInterface;
use DateTimeImmutable;
use Override;

/**
 * Event: Wird geworfen, wenn eine Rechte-Rolle gelöscht wurde.
 */
final readonly class RoleDeletedEvent implements DomainEventInterface
{
    public function __construct(
        public string $roleId,
        public DateTimeImmutable $occurredOn,
    ) {
    }

    #[Override]
    public function getOccurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
