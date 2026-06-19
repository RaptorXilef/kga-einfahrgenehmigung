<?php

declare(strict_types=1);

namespace App\Application\DTO;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class ExportRequest
{
    private function __construct(
        public string $format,
        public string $start,
        public string $end,
    ) {
    }

    public static function fromArray(array $get): self
    {
        return new self(
            (string) ($get['export'] ?? 'csv'),
            (string) ($get['start'] ?? 'all'),
            (string) ($get['end'] ?? 'all'),
        );
    }
}
