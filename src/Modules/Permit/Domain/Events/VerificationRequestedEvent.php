<?php

declare(strict_types=1);

namespace App\Modules\Permit\Domain\Events;

use App\SharedKernel\Domain\Event\DomainEventInterface;
use DateTimeImmutable;
use Override;

/**
 * Event: Wird geworfen, wenn ein Nutzer das Antragsformular absendet und seine Mail verifizieren muss.
 */
final readonly class VerificationRequestedEvent implements DomainEventInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public array $data,
        public string $token,
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
