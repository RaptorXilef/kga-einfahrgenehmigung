<?php

declare(strict_types=1);

namespace App\Modules\Permit\Domain\Events;

use App\Modules\Permit\Domain\Permit;

/**
 * Event: Wird geworfen, sobald eine neue Genehmigung erfolgreich erstellt wurde.
 */
final readonly class PermitCreatedEvent
{
    public function __construct(
        public Permit $permit,
        public string $shortCode,
    ) {
    }
}
