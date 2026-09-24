<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\GetBackupsData;

/**
 * 100% logikfreies View-DTO für ein einzelnes ZIP-Backup im Admin-Dashboard.
 */
final readonly class BackupItemViewDto
{
    /**
     * @param string[] $tables
     */
    public function __construct(
        public string $filename,
        public string $sizeMb,
        public string $dateFormatted,
        public string $targetLabel,
        public string $targetIconUrl,
        public array $tables,
    ) {
    }
}
