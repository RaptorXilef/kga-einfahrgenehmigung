<?php

declare(strict_types=1);

namespace App\Core\Event;

use App\Modules\Permit\Domain\Permit;

/**
 * Event: Wird geworfen, sobald eine neue Genehmigung erfolgreich erstellt wurde.
 */
final readonly class PermitCreatedEvent
{
    public function __construct(
        public Permit $permit, // Nutzt jetzt die neue DDD Entity!
        public string $shortCode,
    ) {
    }
}
