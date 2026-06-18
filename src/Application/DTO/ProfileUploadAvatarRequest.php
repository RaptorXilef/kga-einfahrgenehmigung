<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Application\Exception\ValidationException;

/**
 * DTO für den Upload des eigenen Profilbilds.
 *
 * Path: src/Application/DTO/ProfileUploadAvatarRequest.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class ProfileUploadAvatarRequest
{
    private function __construct(
        public array $file,
    ) {
    }

    public static function fromFiles(array $files): self
    {
        $file = $files['avatar'] ?? null;
        if (! $file || ! isset($file['error']) || $file['error'] !== 0) {
            throw ValidationException::withMessage('Fehler: Es wurde keine gültige Bilddatei hochgeladen.');
        }

        return new self($file);
    }
}
