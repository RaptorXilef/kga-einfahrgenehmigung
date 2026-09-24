<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\GetBackupsData;

final readonly class BackupViewDto
{
    public function __construct(
        public string $filename,
        public float $sizeMb,
        public string $dateFormatted,
        public string $targetName,
        public string $targetLabel,
        public string $targetIconUrl,
        public array $tables,
    ) {
    }
}
