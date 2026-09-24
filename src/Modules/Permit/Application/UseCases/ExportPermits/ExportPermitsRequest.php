<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\ExportPermits;

use App\Application\Http\ServerRequest;
use App\Contracts\Utils\ClockInterface;

final readonly class ExportPermitsRequest
{
    private function __construct(
        public string $state,
        public string $start,
        public string $end,
        public string $type,
        public string $searchQuery,
    ) {
    }

    public static function fromRequest(ServerRequest $request, array $sessionFilters, ClockInterface $clock): self
    {
        $input = $request->getMethod() === 'POST' ? $request->post : $request->get;

        $state = (string) ($input['state'] ?? 'all');
        $start = (string) ($input['start'] ?? 'all');
        $end = (string) ($input['end'] ?? 'all');
        $type = (string) ($input['type'] ?? $sessionFilters['type'] ?? 'all');
        $searchQuery = (string) ($input['q'] ?? $sessionFilters['q'] ?? '');

        if ($start === 'all') {
            $start = $sessionFilters['start'] ?? $clock->now()->format('Y-01-01');
        }

        if ($end === 'all') {
            $end = $sessionFilters['end'] ?? $clock->now()->format('Y-12-31');
        }

        return new self($state, $start, $end, $type, $searchQuery);
    }
}
