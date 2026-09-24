<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\GetBackupsData;

/**
 * Kapselt alle Anzeige-Optionen und die Liste vorhandener Backups für den Tab "Backups".
 */
final readonly class BackupsResultDto
{
    /**
     * @param BackupItemViewDto[] $items
     * @param array<int, array{value: string, label: string}> $targetOptions
     */
    public function __construct(
        public bool $ftpEnabled,
        public array $targetOptions,
        public array $items,
    ) {
    }
}
