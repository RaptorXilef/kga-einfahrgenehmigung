<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\ViewDashboard;

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

    public static function fromArray(array $post): self
    {
        return new self(
            (string) ($post['start'] ?? ''),
            (string) ($post['end'] ?? ''),
            (int) ($post['limit'] ?? 25),
            \trim((string) ($post['q'] ?? '')),
            (string) ($post['type'] ?? 'all'),
        );
    }
}
