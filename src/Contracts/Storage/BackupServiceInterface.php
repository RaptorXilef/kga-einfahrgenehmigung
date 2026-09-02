<?php

declare(strict_types=1);

namespace App\Contracts\Storage;

/**
 * Vertrag für das Backup-System.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
interface BackupServiceInterface
{
    public function runCronBackup(): void;

    public function createBackup(string $target = 'all'): string;

    public function restoreBackup(string $filename, int $mode, string $target = 'all'): void;

    public function listBackups(): array;
}
