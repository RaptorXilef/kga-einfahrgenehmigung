<?php

declare(strict_types=1);

namespace App\Core\Entity;

/**
 * Domain Entity für eine Berechtigungsgruppe.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class Group
{
    public function __construct(
        public string $id,
        public string $name,
        public array $permissions,
    ) {
    }
}
