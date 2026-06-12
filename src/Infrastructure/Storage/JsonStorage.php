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

    // --- Public Write ---

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
        // Sicherer Datei-Handle-Lock zur Vermeidung von Lese-/Schreibkonflikten (Race Conditions)
        $fp = \fopen($this->filePath, 'c+');
        if (! $fp) {
            return false;
        }

        // Warte, bis wir exklusiven Zugriff (Schreibrecht) auf die Datei haben
        if (\flock($fp, \LOCK_EX)) {
            // Hole die aktuellen Daten, während die Datei gesperrt ist
            $stat = \fstat($fp);
            $size = $stat['size'];
            $raw  = $size > 0 ? \fread($fp, $size) : '';
            $data = \json_decode((string) $raw, true) ?? [];

            // Füge die abgeflachte Entität hinzu
            $data[$permit->code] = $this->flattenEntity($permit);

            // Inhalt leeren und neu schreiben
            \ftruncate($fp, 0);
            \fseek($fp, 0);
            $jsonStr = \json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE);
            if (\fwrite($fp, $jsonStr) === false) {
                throw new \RuntimeException('Kritischer Schreibfehler in JsonStorage: Festplatte voll?');
            }
            \fflush($fp);            // Erzwinge die physische Ausgabe auf die Festplatte
            \flock($fp, \LOCK_UN);   // Sperre aufheben
            \fclose($fp);

            return true;
        }

        \fclose($fp);

        return false;
    }

    /**
     * Löscht eine Genehmigung unwiderruflich aus der JSON-Datei.
     *
     * @param string $code Der eindeutige Hash/Code der Genehmigung.
     *
     * @return bool True, wenn der Datensatz erfolgreich aus dem Array entfernt und gespeichert wurde.
     */
    public function delete(string $code): bool
    {
        $fp = \fopen($this->filePath, 'c+');
        if (! $fp) {
            return false;
        }
        if (\flock($fp, \LOCK_EX)) {
            $stat = \fstat($fp);
            $size = $stat['size'];
            $raw  = $size > 0 ? \fread($fp, $size) : '';
            $data = \json_decode((string) $raw, true) ?? [];

            $isDeleted = false; // Status-Tracking

            if (isset($data[$code])) {
                unset($data[$code]);
                \ftruncate($fp, 0);
                \fseek($fp, 0);
                $jsonStr = \json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE);
                if (\fwrite($fp, $jsonStr) === false) {
                    throw new \RuntimeException('Kritischer Schreibfehler in JsonStorage: Festplatte voll?');
                }
                $isDeleted = true; // Nur true, wenn wirklich gelöscht
            }
            \fflush($fp);
            \flock($fp, \LOCK_UN);
            \fclose($fp);

            return $isDeleted; // Korrekten Status zurückgeben
        }
        \fclose($fp);

        return false;
    }

    // --- Public Read ---

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

        // 1. Direkter Match (falls exakter kurzer oder langer Code eingegeben wurde)
        if (isset($data[$hash])) {
            return $this->mapToEntity($data[$hash]);
        }

        // 2. Extrahiere die hinterste ID für den formatübergreifenden Vergleich
        $searchParts = \explode('-', $hash);
        $searchId    = \end($searchParts);

        foreach ($data as $item) {
            $itemParts = \explode('-', (string) $item['code']);
            $itemId    = \end($itemParts);

            if ($itemId === $searchId) {
                return $this->mapToEntity($item);
            }
        }

        return null;
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
        \usort($candidates, function (Permit $a, Permit $b): int {
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

    /**
     * Lädt den gesamten JSON-Inhalt und konvertiert alle Zeilen in starke Permit-Entitäten.
     *
     * @return array<int, Permit> Liste aller Genehmigungen im Dokument.
     */
    public function getAll(): array
    {
        return \array_map($this->mapToEntity(...), $this->loadRaw());
    }

    // --- Public Migrations ---

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

    // --- Private Loader ---

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
}
