<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Application\Exception\ValidationException;

/**
 * DTO für das Umbenennen eines Benutzers.
 *
 * Path: src/Application/DTO/UserRenameRequest.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class UserRenameRequest
{
    private function __construct(
        public string $userId,
        public string $newUsername
    ) {
    }

    // TODO DOCBLOCK
    public static function fromArray(array $post): self
    {
        $userId   = (string) ($post['user_id'] ?? '');
        $newName  = \trim((string) ($post['new_username'] ?? ''));

        if ($userId === '') {
            throw ValidationException::withMessage('Fehler: Kein Benutzer ausgewählt.');
        }
        if ($newName === '') {
            throw ValidationException::withMessage('Fehler: Der neue Login-Name darf nicht leer sein.');
        }

        return new self($userId, $newName);
    }
}
