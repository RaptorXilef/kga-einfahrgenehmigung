<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

// Path: src/Infrastructure/Storage/MySqlStorage.php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Storage\StorageInterface;
use App\Core\Entity\Permit;

/**
 * MySQL-Implementierung des Storage-Interfaces.
 */
final readonly class MySqlStorage implements StorageInterface
{
    use StorageMapperTrait;

    public function __construct(private \PDO $pdo)
    {
    }

    public function save(Permit $permit): bool
    {
        $sql = 'REPLACE INTO permits (
            code, templateKey, name, email, kennzeichen, parzelle, typ,
            firma, zweck, preisSnapshot, von, bis, status, isSuspended,
            suspensionReason, erstellt, internerKommentar
        ) VALUES (
            :code, :templateKey, :name, :email, :kennzeichen, :parzelle, :typ,
            :firma, :zweck, :preisSnapshot, :von, :bis, :status, :isSuspended,
            :suspensionReason, :erstellt, :internerKommentar
        )';

        // Trait liefert das fertige Array für PDO
        return $this->pdo->prepare($sql)->execute($this->flattenEntity($permit));
    }

    public function findByHash(string $hash): ?Permit
    {
        $stmt = $this->pdo->prepare('SELECT * FROM permits WHERE code = ?');
        $stmt->execute([$hash]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? $this->mapToEntity($row) : null;
    }

    /**
     * Findet eine Genehmigung anhand des Kennzeichens.
     * Implementiert für v0.24.5 zur Erfüllung des StorageInterface.
     */
    public function findByLicensePlate(string $plate): ?Permit
    {
        $searchPlate = \preg_replace('/[^A-Z0-9]/', '', \strtoupper($plate));

        if ($searchPlate === '') {
            return null;
        }

        // Wir nutzen SQL REPLACE, um Leerzeichen und Bindestriche in der DB beim Vergleich zu ignorieren
        $stmt = $this->pdo->prepare("
            SELECT * FROM permits
            WHERE REPLACE(REPLACE(kennzeichen, ' ', ''), '-', '') = ?
        ");
        $stmt->execute([$searchPlate]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (! $rows) {
            return null;
        }

        // Mapping der Datenbankzeilen auf Entities
        $candidates = \array_map($this->mapToEntity(...), $rows);

        // Sortierung wie in JsonStorage:
        // 1. Aktive Genehmigungen zuerst
        // 2. Dann nach dem Enddatum (neueste zuerst)
        \usort($candidates, function (Permit $a, Permit $b) {
            $aValid = $a->isValid();
            $bValid = $b->isValid();

            if ($aValid && ! $bValid) {
                return -1;
            }
            if (! $aValid && $bValid) {
                return 1;
            }

            return $b->validity->bis <=> $a->validity->bis;
        });

        return $candidates[0];
    }

    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM permits');
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return \array_map($this->mapToEntity(...), $rows);
    }

    public function migrateTo(StorageInterface $target): int
    {
        $count = 0;
        foreach ($this->getAll() as $permit) {
            if (! $target->save($permit)) {
                continue;
            }

            ++$count;
        }

        return $count;
    }
}
