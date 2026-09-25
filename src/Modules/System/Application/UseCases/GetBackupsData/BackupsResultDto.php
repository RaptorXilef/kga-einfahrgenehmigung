<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\GetBackupsData;

/**
 * 100% logikfreies Container-DTO für den Tab "Backups" (templates/partials/admin/tab_backup.phtml).
 */
final readonly class BackupsResultDto
{
    /**
     * @param BackupItemViewDto[] $items
     * @param array<int, array{value: string, label: string}> $targetOptions
     * @param array<int, array{value: string, label: string}> $truncatableTargetOptions
     */
    public function __construct(
        public array $items,
        public array $targetOptions,
        public array $truncatableTargetOptions,
        public bool $ftpEnabled,
    ) {
    }
}
