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
use App\Core\Entity\Permit;

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
            'name'              => $permit->name,
            'email'             => $permit->email,
            'kennzeichen'       => $permit->kennzeichen,
            'parzelle'          => $permit->parzelle,
            'typ'               => $permit->typ, // Sicherstellen dass Typ da ist
            'firma'             => $permit->firma,
            'zweck'             => $permit->zweck,
            'von'               => $permit->von->format('Y-m-d'),
            'bis'               => $permit->bis->format('Y-m-d'),
            'status'            => $permit->status,
            'erstellt'          => $permit->erstellt->format('Y-m-d H:i:s'),
            'preisSnapshot'     => $permit->preisSnapshot,
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
    private function mapToEntity(array $item): Permit
    {
        return new Permit(
            code: (string) $item['code'],
            name: (string) $item['name'],
            email: (string) $item['email'],
            parzelle: (string) $item['parzelle'],
            typ: (string) ($item['typ'] ?? 'pkw'),
            kennzeichen: (string) $item['kennzeichen'],
            firma: isset($item['firma']) ? (string) $item['firma'] : null,
            zweck: (string) ($item['zweck'] ?? 'Privat'),
            preisSnapshot: (float) ($item['preisSnapshot'] ?? 0.0),
            von: new \DateTimeImmutable((string) $item['von']),
            bis: new \DateTimeImmutable((string) $item['bis']),
            status: (string) $item['status'],
            erstellt: new \DateTimeImmutable((string) ($item['erstellt'] ?? 'now')),
            internerKommentar: isset($item['internerKommentar']) ? (string) $item['internerKommentar'] : null, // NEU
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
