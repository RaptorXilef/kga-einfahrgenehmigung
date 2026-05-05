<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

/**
 * Path: src/Core/Entity/Validity.php
 */

declare(strict_types=1);

namespace App\Core\Entity;

final readonly class Validity
{
    public function __construct(
        public \DateTimeImmutable $von,
        public \DateTimeImmutable $bis,
        public float $preisSnapshot, // Der Preis zum Zeitpunkt der Buchung / Wichtig für die Finanzstatistik
        public string $zweck,
    ) {
    }
}
