<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * JSON-Implementierung des Storage-Interfaces.
 *
 * Verwaltet den Lese- und Schreibzugriff auf die lokale daten.json.
 *
 * @file      src/Infrastructure/Storage/JsonStorage.php
 *
 * @since     0.1.0
 * - feat(storage): Implementierung der JSON-Persistenz.
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
            'code'        => $permit->code,
            'name'        => $permit->name,
            'email'       => $permit->email,
            'kennzeichen' => $permit->kennzeichen,
            'parzelle'    => $permit->parzelle,
            'von'         => $permit->von->format('Y-m-d'),
            'bis'         => $permit->bis->format('Y-m-d'),
            'status'      => $permit->status,
            'erstellt'    => $permit->erstellt?->format('Y-m-d H:i:s'),
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

    private function mapToEntity(array $item): Permit
    {
        return new Permit(
            code: (string) $item['code'],
            name: (string) $item['name'],
            email: (string) $item['email'],
            parzelle: (string) $item['parzelle'],
            typ: (string) ($item['typ'] ?? 'pkw'), // Neu
            kennzeichen: (string) $item['kennzeichen'],
            firma: $item['firma'] ?? null,           // Neu
            zweck: (string) ($item['zweck'] ?? 'Privat'), // Neu
            preisSnapshot: (float) ($item['preisSnapshot'] ?? 0.0), // Neu
            von: new \DateTimeImmutable($item['von']),
            bis: new \DateTimeImmutable($item['bis']),
            status: (string) $item['status'],
            erstellt: new \DateTimeImmutable($item['erstellt'] ?? 'now'),
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

    private function loadRaw(): array
    {
        if (! \file_exists($this->filePath)) {
            return [];
        }

        return \json_decode(\file_get_contents($this->filePath), true) ?: [];
    }
}
