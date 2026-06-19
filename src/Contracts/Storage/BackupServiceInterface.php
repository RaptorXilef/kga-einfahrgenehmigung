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
    public function createBackup(string $target): string;

    public function listBackups(): array;

    public function getBackupData(string $timestamp, string $target): ?array;

    public function checkAutoBackup(): void;
}
