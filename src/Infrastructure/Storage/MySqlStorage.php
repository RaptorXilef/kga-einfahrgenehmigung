<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * MySQL-Implementierung des Storage-Interfaces.
 *
 * @file src/Infrastructure/Storage/MySqlStorage.php
 */

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Storage\StorageInterface;
use App\Core\Entity\Permit;

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
            if (!$target->save($permit)) {
                continue;
            }

            ++$count;
        }

        return $count;
    }
}
