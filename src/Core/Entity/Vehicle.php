<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

/**
 * Path: src/Core/Entity/Vehicle.php
 */

declare(strict_types=1);

namespace App\Core\Entity;

final readonly class Vehicle
{
    public function __construct(
        public string $typ,           // pkw, lkw
        public string $kennzeichen,   // im Format B-XX 1234
        public ?string $firma = null, // Optional für LKW
    ) {
    }
}
