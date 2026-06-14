<?php

declare(strict_types=1);

namespace App\Contracts\Storage;

/**
 * Kapselt Locking-Mechanismen (Dateisystem, Redis, etc.) für atomare Prozesse.
 *
 * Path: src/Contracts/Storage/LockManagerInterface.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
interface LockManagerInterface
{
    /**
     * Führt einen Prozess atomar (mit Lock) aus.
     *
     * @param string   $lockName  Identifier des Locks.
     * @param callable $operation Die auszuführende Operation.
     *
     * @return mixed Rückgabe der Operation.
     */
    public function executeWithLock(string $lockName, callable $operation): mixed;
}
