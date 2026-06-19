<?php

declare(strict_types=1);

namespace App\Application\DTO;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class SimpleCodeRequest
{
    private function __construct(
        public string $code,
        public string $token,
    ) {
    }

    public static function fromArray(array $get): self
    {
        return new self(
            \strtoupper(\trim((string) ($get['code'] ?? ''))),
            (string) ($get['token'] ?? ''),
        );
    }
}
