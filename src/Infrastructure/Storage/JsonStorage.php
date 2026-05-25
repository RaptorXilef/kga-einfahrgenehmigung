<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Storage\StorageInterface;
use App\Core\Entity\Permit;

/**
 * JSON-Implementierung des Storage-Interfaces.
 *
 * Verwaltet den Lese- und Schreibzugriff auf die lokale permits_active.json.
 *
 * Persistenz-Engine für dateibasierte Datenhaltung im JSON-Format.
 * Implementiert das StorageInterface und nutzt exklusive Dateisperren (`LOCK_EX`)
 * beim Schreiben sowie Kennzeichen-Suchalgorithmen mit Relevanz-Sortierung.
 * Kontext: Leichtgewichtiges NoSQL-Datei-Backend für kleine Umgebungen ohne MySQL-Server.
 *
 * Path: src/Infrastructure/Storage/JsonStorage.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class JsonStorage implements StorageInterface
{
    use StorageMapperTrait;

    /**
     * @param string $filePath Absoluter oder relativer Pfad zur JSON-Zieldatei auf dem Server.
     */
    public function __construct(
        private string $filePath,
    ) {
    }

    /**
     * Serialisiert und speichert eine Genehmigungs-Entität in der JSON-Datei.
     * Verwendet das StorageMapperTrait zum Abflachen der Objektstruktur.
     *
     * @param Permit $permit Das zu speichernde Permit-Objekt.
     *
     * @return bool True, wenn der Schreibvorgang erfolgreich war.
     */
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

    /**
     * Sucht eine Genehmigung anhand des vollständigen Codes oder des zufälligen Suffix-Endstücks.
     *
     * @param string $hash Der Code oder Suffix-Teilstring für die Suche.
     *
     * @return Permit|null Die hydrierte Entität oder null, falls kein Treffer erzielt wurde.
     */
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
     * Lädt den gesamten JSON-Inhalt und konvertiert alle Zeilen in starke Permit-Entitäten.
     *
     * @return array<int, Permit> Liste aller Genehmigungen im Dokument.
     */
    public function getAll(): array
    {
        return \array_map($this->mapToEntity(...), $this->loadRaw());
    }

    /**
     * Migriert alle enthaltenen JSON-Datensätze in eine andere Storage-Ziel-Engine.
     *
     * @param StorageInterface $target Das Ziel-Repository (z.B. MySqlStorage).
     *
     * @return int Die Anzahl erfolgreich migrierter Datensätze.
     */
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
     * Liest die rohen, unstrukturierten JSON-Arrayinhalte direkt von der Festplatte.
     *
     * @return array<string, mixed> Das assoziative Rohdaten-Array.
     */
    private function loadRaw(): array
    {
        if (! \file_exists($this->filePath)) {
            return [];
        }

        return \json_decode((string) \file_get_contents($this->filePath), true) ?? [];
    }

    /**
     * Findet eine Genehmigung über das amtliche Kennzeichen.
     * Bereinigt Leer- und Sonderzeichen für den Abgleich und sortiert bei Mehrfachtreffern
     * nach Priorität: Aktive/Gültige zuerst, danach absteigend nach dem Ablaufdatum (`bis`).
     *
     * @param string $plate Das gesuchte Fahrzeugkennzeichen.
     *
     * @return Permit|null Die relevanteste passende Genehmigung oder null.
     */
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
            if ($storedPlate !== $searchPlate) {
                continue;
            }

            $candidates[] = $permit;
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
