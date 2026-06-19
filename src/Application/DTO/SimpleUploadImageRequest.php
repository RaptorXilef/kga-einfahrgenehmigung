<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Application\Exception\ValidationException;

/**
 * Generisches DTO für Upload-Actions, die eine Ziel-ID und ein Datei-Array erwarten.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class SimpleUploadImageRequest
{
    private function __construct(
        public string $identifier,
        public array $file,
    ) {
    }

    public static function fromRequest(array $post, string $keyName, array $files): self
    {
        $identifier = \trim((string) ($post[$keyName] ?? ''));

        if ($identifier === '') {
            throw ValidationException::withMessage("Fehler: Fehlender Parameter ($keyName).");
        }

        $file = $files['avatar'] ?? null;
        if (! $file || ! isset($file['error']) || $file['error'] !== 0) {
            throw ValidationException::withMessage('Fehler: Ungültiger oder fehlender Datei-Upload.');
        }

        return new self($identifier, $file);
    }
}
