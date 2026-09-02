<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Application\Http\ServerRequest;

final readonly class ExportRequest
{
    private function __construct(
        public string $format,
        public string $start,
        public string $end,
    ) {
    }

    public static function fromRequest(ServerRequest $request, array $sessionFilters = []): self
    {
        // Dynamisch auslesen, egal ob <form method="POST"> oder <a href="?export="> genutzt wird
        $input = $request->getMethod() === 'POST' ? $request->post : $request->get;

        $start = (string) ($input['start'] ?? 'all');
        $end = (string) ($input['end'] ?? 'all');

        if ($start === 'all') {
            $start = $sessionFilters['start'] ?? \date('Y-01-01');
        }

        if ($end === 'all') {
            $end = $sessionFilters['end'] ?? \date('Y-12-31');
        }

        // Unterstützt den Key "format" oder "export"
        $format = (string) ($input['format'] ?? $input['export'] ?? 'csv');

        return new self($format, $start, $end);
    }
}
