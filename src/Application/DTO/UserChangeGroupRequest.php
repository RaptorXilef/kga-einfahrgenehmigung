<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Application\Exception\ValidationException;

/**
 * TODO DOCBLOCK
 *
 * Path: src/Application/DTO/UserChangeGroupRequest.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class UserChangeGroupRequest
{
    private function __construct(
        public string $userId,
        public string $group,
    ) {
    }

    // TODO DOCBLOCK
    public static function fromArray(array $post): self
    {
        $userId = \trim((string) ($post['user_id'] ?? ''));
        $group  = \trim((string) ($post['group'] ?? ''));

        if ($userId === '') {
            throw ValidationException::withMessage('Fehler: Kein Benutzer ausgewählt.');
        }
        if ($group === '') {
            throw ValidationException::withMessage('Fehler: Keine Gruppe ausgewählt.');
        }

        return new self($userId, $group);
    }
}
