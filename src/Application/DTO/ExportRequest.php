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

    public static function fromArray(array $get, array $sessionFilters = []): self
    {
        $start = (string) ($get['start'] ?? 'all');
        $end   = (string) ($get['end'] ?? 'all');

        if ($start === 'all') {
            $start = $sessionFilters['start'] ?? \date('Y-01-01');
        }

        if ($end === 'all') {
            $end = $sessionFilters['end'] ?? \date('Y-12-31');
        }

        return new self(
            (string) ($get['export'] ?? 'csv'),
            $start,
            $end,
        );
    }
}
