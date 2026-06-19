<?php

declare(strict_types=1);

namespace App\Core\Entity;

/**
 * Domain Entity für einen Administrator/Benutzer.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class User
{
    public function __construct(
        public string $id,
        public string $username,
        public string $groupId,
        public string $passwordHash,
    ) {
    }
}
