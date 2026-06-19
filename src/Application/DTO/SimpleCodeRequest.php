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
    public function __construct(
        public string $code,
        public string $token,
        public bool $hasCode,
    ) {
    }

    public static function fromArray(array $get): self
    {
        $code = \strtoupper(\trim((string) ($get['code'] ?? '')));

        return new self($code, (string) ($get['token'] ?? ''), $code !== '');
    }
}
