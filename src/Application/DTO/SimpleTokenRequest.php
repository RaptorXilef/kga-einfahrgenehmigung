<?php

declare(strict_types=1);

namespace App\Application\DTO;

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
        return new self(\trim((string) ($get['token'] ?? '')));
    }
}
