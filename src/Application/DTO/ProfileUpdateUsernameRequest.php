<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Application\Exception\ValidationException;

/**
 * DTO für das Ändern des eigenen Anzeigenamens.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class ProfileUpdateUsernameRequest
{
    private function __construct(
        public string $newUsername,
    ) {
    }

    /**
     * @param  array<string, mixed> $post
     * @throws ValidationException
     */
    public static function fromArray(array $post): self
    {
        $newName = \trim((string) ($post['new_username'] ?? ''));

        if ($newName === '') {
            throw ValidationException::withMessage('Fehler: Name darf nicht leer sein.');
        }

        return new self($newName);
    }
}
