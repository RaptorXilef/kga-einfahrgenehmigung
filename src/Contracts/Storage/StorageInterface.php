<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * Interface für Datenspeicher-Provider.
 *
 * Definiert die Verträge für JSON- und MySQL-Implementierungen.
 *
 * @file      src/Contracts/Storage/StorageInterface.php
 *
 * @since     0.1.0
 * - feat(storage): Definition der Persistenz-Schnittstelle.
 */

declare(strict_types=1);

namespace App\Contracts\Storage;

use App\Core\Entity\Permit;

interface StorageInterface
{
    public function save(Permit $permit): bool;

    public function findByHash(string $hash): ?Permit;

    /**
     * @return Permit[]
     */
    public function getAll(): array;

    public function migrateTo(StorageInterface $target): int;

    /**
     * Wandelt ein Roh-Array (aus JSON/DB) in ein Permit-Objekt um.
     *
     * @param array<string, mixed> $item
     */
    public function mapToEntity(array $item): Permit;
}
