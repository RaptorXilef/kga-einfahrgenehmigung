<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\GetBackupsData;

/**
 * 100% logikfreies View-DTO für ein einzelnes ZIP-Backup im Dashboard-Tab "Backups".
 * Sortiert nach dem semantischen DDD-Standard (1. Identifier, 2. Kerndaten, 3. UI/Assets, 4. Collections).
 */
final readonly class BackupItemViewDto
{
    /**
     * @param string[] $tables
     */
    public function __construct(
        public string $filename,
        public string $dateFormatted,
        public string $sizeMb,
        public string $targetLabel,
        public string $targetIconUrl,
        public array $tables,
    ) {
    }
}
