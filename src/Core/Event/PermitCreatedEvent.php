<?php

declare(strict_types=1);

namespace App\Core\Event;

use App\Core\Entity\Permit;

/**
 * Event: Wird geworfen, sobald eine neue Genehmigung erfolgreich erstellt wurde.
 *
 * Path: src/Core/Event/PermitCreatedEvent.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class PermitCreatedEvent
{
    public function __construct(
        public Permit $permit,
        public string $shortCode,
    ) {
    }
}
