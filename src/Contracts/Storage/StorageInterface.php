<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

/**
 * Interface für Datenspeicher-Provider.
 *
 * Definiert die Verträge für JSON- und MySQL-Implementierungen.
 *
 * Path: src/Contracts/Storage/StorageInterface.php
 */

declare(strict_types=1);

namespace App\Contracts\Storage;

use App\Core\Entity\Permit;

interface StorageInterface
{
    public function save(Permit $permit): bool;

    /**
     * Sucht die Genehmigung für ein Hash.
     */
    public function findByHash(string $hash): ?Permit;

    /**
     * Sucht die relevanteste Genehmigung für ein Kennzeichen.
     */
    public function findByLicensePlate(string $plate): ?Permit;

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
