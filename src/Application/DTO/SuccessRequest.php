<?php

declare(strict_types=1);

namespace App\Application\DTO;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class SuccessRequest
{
    private function __construct(
        public string $code,
        public string $method,
    ) {
    }

    public static function fromArray(array $get): self
    {
        return new self(
            \trim((string) ($get['code'] ?? '')),
            (string) ($get['method'] ?? 'wire'),
        );
    }
}
