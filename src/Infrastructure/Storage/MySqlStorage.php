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
use App\Core\Entity\Owner;
use App\Core\Entity\Permit;
use App\Core\Entity\Status;
use App\Core\Entity\Validity;
use App\Core\Entity\Vehicle;

final readonly class MySqlStorage implements StorageInterface
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function save(Permit $permit): bool
    {
        // FIX: firma und preisSnapshot im SQL hinzugefügt
        $sql = 'REPLACE INTO permits (
            code, templateKey, name, email, kennzeichen, parzelle, typ,
            firma, zweck, preisSnapshot, von, bis, status, isSuspended,
            suspensionReason, erstellt, internerKommentar
        ) VALUES (
            :code, :templateKey, :name, :email, :kennzeichen, :parzelle, :typ,
            :firma, :zweck, :preisSnapshot, :von, :bis, :status, :isSuspended,
            :suspensionReason, :erstellt, :internerKommentar
        )';

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            'code'              => $permit->code,
            'templateKey'       => $permit->templateKey,
            'name'              => $permit->owner->name,
            'email'             => $permit->owner->email,
            'kennzeichen'       => $permit->vehicle->kennzeichen,
            'parzelle'          => $permit->owner->parzelle,
            'typ'               => $permit->vehicle->typ,
            'firma'             => $permit->vehicle->firma,
            'zweck'             => $permit->validity->zweck,
            'preisSnapshot'     => $permit->validity->preisSnapshot,
            'von'               => $permit->validity->von->format('Y-m-d'),
            'bis'               => $permit->validity->bis->format('Y-m-d'),
            'status'            => $permit->status->current,
            'isSuspended'       => (int) $permit->status->isSuspended,
            'suspensionReason'  => $permit->status->suspensionReason,
            'erstellt'          => $permit->erstellt->format('Y-m-d H:i:s'),
            'internerKommentar' => $permit->internerKommentar,
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

        return $this->mapToEntity($row);
    }

    /**
     * @param array<string, mixed> $item
     */
    public function mapToEntity(array $item): Permit
    {
        return new Permit(
            code: (string) $item['code'],
            templateKey: (string) ($item['templateKey'] ?? 'std_7'),
            owner: new Owner(
                (string) $item['name'],
                (string) $item['email'],
                (string) $item['parzelle'],
            ),
            vehicle: new Vehicle(
                (string) ($item['typ'] ?? 'pkw'),
                (string) $item['kennzeichen'],
                $item['firma'] ?? null,
            ),
            validity: new Validity(
                new \DateTimeImmutable((string) $item['von']),
                new \DateTimeImmutable((string) $item['bis']),
                (float) ($item['preisSnapshot'] ?? 0.0),
                (string) ($item['zweck'] ?? 'Privat'),
            ),
            status: new Status(
                (string) ($item['status'] ?? 'wartend'),
                (bool) ($item['isSuspended'] ?? false),
                $item['suspensionReason'] ?? null,
            ),
            erstellt: new \DateTimeImmutable((string) ($item['erstellt'] ?? 'now')),
            internerKommentar: $item['internerKommentar'] ?? null,
        );
    }

    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM permits');
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return \array_map(
            fn (array $row): Permit => $this->mapToEntity($row),
            $rows,
        );
    }

    public function migrateTo(StorageInterface $target): int
    {
        $count = 0;
        foreach ($this->getAll() as $permit) {
            if ($target->save($permit)) {
                ++$count;
            }
        }

        return $count;
    }
}
