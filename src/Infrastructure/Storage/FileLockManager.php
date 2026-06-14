<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\LockManagerInterface;

/**
 * TODO DOCBLOCK
 *
 * Path: src/Infrastructure/Storage/FileLockManager.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class FileLockManager implements LockManagerInterface
{
    public function __construct(private ConfigInterface $config)
    {
    }

    // TODO DOCBLOCK
    public function executeWithLock(string $lockName, callable $operation): mixed
    {
        $lockFile = $this->config->getStoragePath("logs/{$lockName}.lock");
        $fp       = @\fopen($lockFile, 'c');

        if ($fp) {
            \flock($fp, \LOCK_EX);
        }

        try {
            return $operation();
        } finally {
            if ($fp) {
                \flock($fp, \LOCK_UN);
                \fclose($fp);
            }
        }
    }
}
