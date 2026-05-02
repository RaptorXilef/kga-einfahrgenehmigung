<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * JSON-Implementierung des Storage-Interfaces.
 *
 * Verwaltet den Lese- und Schreibzugriff auf die lokale daten.json.
 *
 * @file      src/Infrastructure/Storage/JsonStorage.php
 */

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Storage\StorageInterface;
use App\Core\Entity\Owner;
use App\Core\Entity\Permit;
use App\Core\Entity\Status;
use App\Core\Entity\Validity;
use App\Core\Entity\Vehicle;

final readonly class JsonStorage implements StorageInterface
{
    public function __construct(
        private string $filePath,
    ) {
    }

    public function save(Permit $permit): bool
    {
        $data                = $this->loadRaw();
        $data[$permit->code] = [
            'code'              => $permit->code,
            'templateKey'       => $permit->templateKey,
            'name'              => $permit->owner->name,
            'email'             => $permit->owner->email,
            'parzelle'          => $permit->owner->parzelle,
            'typ'               => $permit->vehicle->typ, // Sicherstellen dass Typ da ist
            'kennzeichen'       => $permit->vehicle->kennzeichen,
            'firma'             => $permit->vehicle->firma,
            'von'               => $permit->validity->von->format('Y-m-d'),
            'bis'               => $permit->validity->bis->format('Y-m-d'),
            'preisSnapshot'     => $permit->validity->preisSnapshot,
            'zweck'             => $permit->validity->zweck,
            'status'            => $permit->status->current,
            'isSuspended'       => $permit->status->isSuspended,
            'suspensionReason'  => $permit->status->suspensionReason,
            'erstellt'          => $permit->erstellt->format('Y-m-d H:i:s'),
            'internerKommentar' => $permit->internerKommentar,
        ];

        return (bool) \file_put_contents(
            $this->filePath,
            \json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE),
            \LOCK_EX,
        );
    }

    public function findByHash(string $hash): ?Permit
    {
        $data = $this->loadRaw();
        $hash = \strtoupper(\trim($hash));

        // 1. Exakter Match (ML-0020-B-HD-123-6Y5C)
        if (isset($data[$hash])) {
            return $this->mapToEntity($data[$hash]);
        }

        // 2. Teil-Match (Suche nach der 4-stelligen Zufalls-ID am Ende)
        foreach ($data as $item) {
            if (\str_ends_with((string) $item['code'], $hash)) {
                return $this->mapToEntity($item);
            }
        }

        return null;
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
                (string) $item['status'],
                (bool) ($item['isSuspended'] ?? false),
                $item['suspensionReason'] ?? null,
            ),
            erstellt: new \DateTimeImmutable((string) ($item['erstellt'] ?? 'now')),
            internerKommentar: $item['internerKommentar'] ?? null,
        );
    }

    public function getAll(): array
    {
        return \array_map(fn (array $item): ?Permit => $this->findByHash($item['code']), $this->loadRaw());
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

    /**
     * @return array<string, mixed>
     */
    private function loadRaw(): array
    {
        if (! \file_exists($this->filePath)) {
            return [];
        }

        return \json_decode(\file_get_contents($this->filePath), true) ?? [];
    }
}
