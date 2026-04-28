<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * MySQL-Implementierung des Storage-Interfaces.
 *
 * @file      src/Infrastructure/Storage/MySqlStorage.php
 */

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Storage\StorageInterface;
use App\Core\Entity\Permit;

final readonly class MySqlStorage implements StorageInterface
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function save(Permit $permit): bool
    {
        // FIX: firma und preisSnapshot im SQL hinzugefügt
        $sql = 'REPLACE INTO permits (
            code, name, email, kennzeichen, parzelle, typ,
            firma, zweck, preisSnapshot, von, bis, status, erstellt
        ) VALUES (
            :code, :name, :email, :kennzeichen, :parzelle, :typ,
            :firma, :zweck, :preisSnapshot, :von, :bis, :status, :erstellt
        )';

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            'code'          => $permit->code,
            'name'          => $permit->name,
            'email'         => $permit->email,
            'kennzeichen'   => $permit->kennzeichen,
            'parzelle'      => $permit->parzelle,
            'typ'           => $permit->typ,
            'firma'         => $permit->firma,
            'zweck'         => $permit->zweck,
            'preisSnapshot' => $permit->preisSnapshot,
            'von'           => $permit->von->format('Y-m-d'),
            'bis'           => $permit->bis->format('Y-m-d'),
            'status'        => $permit->status,
            'erstellt'      => $permit->erstellt?->format('Y-m-d H:i:s'),
        ]);
    }

    public function findByHash(string $hash): ?Permit
    {
        $stmt = $this->pdo->prepare('SELECT * FROM permits WHERE code = ?');
        $stmt->execute([$hash]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row === false || $row === null) {
            return null;
        }

        return new Permit(
            code: (string) $row['code'],
            name: (string) $row['name'],
            email: (string) $row['email'],
            parzelle: (string) $row['parzelle'],
            typ: (string) ($row['typ'] ?? 'pkw'),
            kennzeichen: (string) $row['kennzeichen'],
            firma: isset($row['firma']) ? (string) $row['firma'] : null,
            zweck: (string) ($row['zweck'] ?? 'Privat'),
            preisSnapshot: (float) ($row['preisSnapshot'] ?? 0.0),
            von: new \DateTimeImmutable((string) $row['von']),
            bis: new \DateTimeImmutable((string) $row['bis']),
            status: (string) $row['status'],
            erstellt: new \DateTimeImmutable((string) $row['erstellt']),
        );
    }

    /**
     * @return Permit[]
     */
    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT code FROM permits');

        // Wir nutzen fetchAll und mappen dann, um PHPStan Typsicherheit zu geben
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return \array_map(
            fn (array $row): ?Permit => $this->findByHash((string) $row['code']),
            $rows,
        );
    }

    public function migrateTo(StorageInterface $target): int
    {
        $count = 0;
        foreach ($this->getAll() as $permit) {
            if (! ($permit instanceof Permit) || ! $target->save($permit)) {
                continue;
            }

            ++$count;
        }

        return $count;
    }
}
