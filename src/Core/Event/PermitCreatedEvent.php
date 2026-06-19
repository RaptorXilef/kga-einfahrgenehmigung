<?php

declare(strict_types=1);

namespace App\Core\Event;

use App\Core\Entity\Permit;

/**
 * Event: Wird geworfen, sobald eine neue Genehmigung erfolgreich erstellt wurde.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class PermitCreatedEvent
{
    public function __construct(
        public Permit $permit,
        public string $shortCode,
    ) {
    }
}
