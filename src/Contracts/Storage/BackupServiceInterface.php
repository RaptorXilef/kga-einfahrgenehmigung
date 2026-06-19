<?php

declare(strict_types=1);

namespace App\Contracts\Storage;

/**
 * Vertrag für das Backup-System.
 *
 * Path: src/Contracts/Storage/BackupServiceInterface.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
interface BackupServiceInterface
{
    public function createBackup(string $target): string;

    public function listBackups(): array;

    public function getBackupData(string $timestamp, string $target): ?array;

    public function checkAutoBackup(): void;
}
