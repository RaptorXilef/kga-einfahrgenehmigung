<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Events;

/**
 * Event: Wird geworfen, wenn eine Rechte-Rolle gelöscht wurde.
 */
final readonly class RoleDeletedEvent
{
    public function __construct(
        public string $roleId,
    ) {
    }
}
