<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Application\Exception\ValidationException;

/**
 * DTO für den Login-Versuch eines Administrators.
 *
 * Path: src/Application/DTO/AdminLoginRequest.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class AdminLoginRequest
{
    private function __construct(
        public string $username,
        public string $password,
    ) {
    }

    // TODO DOCBLOCK
    public static function fromArray(array $post): self
    {
        $user = \trim((string) ($post['user'] ?? ''));
        $pass = (string) ($post['pass'] ?? '');

        if ($user === '' || $pass === '') {
            throw ValidationException::withMessage('Bitte geben Sie einen Benutzernamen und ein Passwort ein.');
        }

        return new self($user, $pass);
    }
}
