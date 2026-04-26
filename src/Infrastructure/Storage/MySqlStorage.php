<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * MySQL-Implementierung des Storage-Interfaces.
 *
 * @file      src/Infrastructure/Storage/MySqlStorage.php
 *
 * @since     0.1.0
 */

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Storage\StorageInterface;
use App\Core\Entity\Permit;
use DateTimeImmutable;
use PDO;

final readonly class MySqlStorage implements StorageInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function save(Permit $permit): bool
    {
        $sql = 'REPLACE INTO permits (code, name, email, kennzeichen, parzelle, typ, zweck, von, bis, status, erstellt)
                VALUES (:code, :name, :email, :kennzeichen, :parzelle, :typ, :zweck, :von, :bis, :status, :erstellt)';

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            'code'        => $permit->code,
            'name'        => $permit->name,
            'email'       => $permit->email,
            'kennzeichen' => $permit->kennzeichen,
            'parzelle'    => $permit->parzelle,
            'typ'         => $permit->typ,
            'zweck'       => $permit->zweck,
            'von'         => $permit->von->format('Y-m-d'),
            'bis'         => $permit->bis->format('Y-m-d'),
            'status'      => $permit->status,
            'erstellt'    => $permit->erstellt?->format('Y-m-d H:i:s'),
        ]);
    }

    public function findByHash(string $hash): ?Permit
    {
        $stmt = $this->pdo->prepare('SELECT * FROM permits WHERE code = ?');
        $stmt->execute([$hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (! $row) {
            return null;
        }

        return new Permit(
            $row['code'],
            $row['name'],
            $row['email'],
            $row['kennzeichen'],
            $row['parzelle'],
            $row['typ'],
            $row['zweck'],
            new DateTimeImmutable($row['von']),
            new DateTimeImmutable($row['bis']),
            $row['status'],
            new DateTimeImmutable($row['erstellt']),
        );
    }

    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT code FROM permits');

        return \array_map(
            fn (array $row): ?Permit => $this->findByHash($row['code']),
            $stmt->fetchAll(PDO::FETCH_ASSOC),
        );
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
