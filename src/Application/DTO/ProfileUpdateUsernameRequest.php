<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Application\Exception\ValidationException;

/**
 * DTO für das Ändern des eigenen Anzeigenamens.
 *
 * Path: src/Application/DTO/ProfileUpdateUsernameRequest.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
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
