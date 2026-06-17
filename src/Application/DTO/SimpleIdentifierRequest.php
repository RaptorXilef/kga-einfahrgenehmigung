<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Application\Exception\ValidationException;

/**
 * Generisches DTO für Aktionen, die nur eine einzige ID oder einen Code benötigen
 * (z.B. Löschen, Umschalten, als bezahlt markieren).
 *
 * Path: src/Application/DTO/SimpleIdentifierRequest.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class SimpleIdentifierRequest
{
    private function __construct(
        public string $identifier,
    ) {
    }

    // TODO DOCBLOCK
    public static function fromArray(array $post, string $keyName): self
    {
        $identifier = \trim((string) ($post[$keyName] ?? ''));

        if ($identifier === '') {
            throw ValidationException::withMessage("Fehler: Fehlender Parameter ($keyName).");
        }

        return new self($identifier);
    }
}
