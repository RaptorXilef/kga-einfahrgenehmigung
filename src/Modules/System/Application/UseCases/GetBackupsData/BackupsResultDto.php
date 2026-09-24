<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\GetBackupsData;

final readonly class BackupsResultDto
{
    public function __construct(
        /**
         * @var BackupViewDto[]
         */
        public array $items,
        public array $targetOptions,
        public bool $ftpEnabled,
    ) {
    }
}
