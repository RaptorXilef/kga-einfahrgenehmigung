<?php

declare(strict_types=1);

namespace App\Core\Entity;

/**
 * Domain Entity für eine Berechtigungsgruppe.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
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
