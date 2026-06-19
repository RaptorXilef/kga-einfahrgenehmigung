<?php

declare(strict_types=1);

namespace App\Contracts\Storage;

/**
 * Kapselt Locking-Mechanismen (Dateisystem, Redis, etc.) für atomare Prozesse.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
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
