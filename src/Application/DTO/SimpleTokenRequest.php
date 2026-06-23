<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Application\Exception\ValidationException;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class SimpleTokenRequest
{
    private function __construct(public string $token)
    {
    }

    public static function fromArray(array $get): self
    {
        $token = \trim((string) ($get['token'] ?? ''));
        if ($token === '') {
            throw ValidationException::withMessage('Fehler: Sicherheits-Token fehlt.');
        }

        return new self($token);
    }
}
