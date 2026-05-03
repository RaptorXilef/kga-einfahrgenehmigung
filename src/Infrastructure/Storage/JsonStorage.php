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
    use StorageMapperTrait;

    public function __construct(
        private string $filePath,
    ) {
    }

    public function save(Permit $permit): bool
    {
        $data = $this->loadRaw();
        // Nutzt den Trait für die Umwandlung
        $data[$permit->code] = $this->flattenEntity($permit);

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

    public function getAll(): array
    {
        return \array_map($this->mapToEntity(...), $this->loadRaw());
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

        return \json_decode((string) \file_get_contents($this->filePath), true) ?? [];
    }

    public function findByLicensePlate(string $plate): ?Permit
    {
        $all         = $this->getAll();
        $searchPlate = \preg_replace('/[^A-Z0-9]/', '', \strtoupper($plate));

        if ($searchPlate === '') {
            return null;
        }

        $candidates = [];

        foreach ($all as $permit) {
            $storedPlate = \preg_replace('/[^A-Z0-9]/', '', \strtoupper($permit->vehicle->kennzeichen));
            if ($storedPlate === $searchPlate) {
                $candidates[] = $permit;
            }
        }

        if ($candidates === []) {
            return null;
        }

        // Sortierung:
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
}
