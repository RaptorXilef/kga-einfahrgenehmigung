<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

// Path: src/Core/Entity/Status.php

declare(strict_types=1);

namespace App\Core\Entity;

/**
 * Repräsentiert den aktuellen Lebenszyklus einer Genehmigung.
 */
final readonly class Status
{
    public function __construct(
        public string $current = 'wartend',       // technischer Status (wartend, bezahlt, storniert)
        public bool $isSuspended = false,         // Manuelle Sperre durch Admin
        public ?string $suspensionReason = null,  // Begründung der Sperre
    ) {
    }
}
