<?php

declare(strict_types=1);

namespace App\Application\DTO;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class DashboardViewRequest
{
    private function __construct(
        public string $start,
        public string $end,
        public string $type,
        public string $query,
        public int $limit,
        public int $page,
        public bool $resetFilters,
    ) {
    }

    public static function fromRequest(array $get, array $sessionFilters, array $paginationCfg): self
    {
        $reset         = isset($get['reset_filters']);
        $actualFilters = $reset ? [] : $sessionFilters;

        $start = (string) ($actualFilters['start'] ?? $get['start'] ?? \date('Y-01-01'));
        $end   = (string) ($actualFilters['end'] ?? $get['end'] ?? \date('Y-12-31'));
        $type  = (string) ($actualFilters['type'] ?? $get['type'] ?? 'all');
        $query = \strtolower(\trim((string) ($actualFilters['q'] ?? $get['q'] ?? '')));

        $allowedLimits = $paginationCfg['allowed_limits'] ?? [10, 25, 50, 100, 250];
        $defaultLimit  = (int) ($paginationCfg['default_limit'] ?? 25);

        $requestedLimit = (int) ($actualFilters['limit'] ?? $get['limit'] ?? $defaultLimit);
        $limit          = \in_array($requestedLimit, $allowedLimits, true) ? $requestedLimit : $defaultLimit;

        $page = \max(1, (int) ($get['page'] ?? 1));

        return new self($start, $end, $type, $query, $limit, $page, $reset);
    }
}
