<?php

declare(strict_types=1);

namespace App\Application\DTO;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class DashboardFilterRequest
{
    private function __construct(
        public string $start,
        public string $end,
        public int $limit,
        public string $q,
        public string $type,
    ) {
    }

    // TODO DOCBLOCK
    public static function fromArray(array $post): self
    {
        // Ein Filter wirft keine Fehler, er nutzt saubere Standardwerte!
        return new self(
            (string) ($post['start'] ?? ''),
            (string) ($post['end'] ?? ''),
            (int) ($post['limit'] ?? 25),
            \trim((string) ($post['q'] ?? '')),
            (string) ($post['type'] ?? 'all'),
        );
    }
}
