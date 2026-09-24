<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\ExportPermits;

final readonly class ExportPermitsResultDto
{
    public function __construct(
        public string $content,
        public string $filename,
        public string $contentType,
    ) {
    }
}
