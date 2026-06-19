<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Application\Exception\ValidationException;

/**
 * DTO für das Umbenennen eines Benutzers.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
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
