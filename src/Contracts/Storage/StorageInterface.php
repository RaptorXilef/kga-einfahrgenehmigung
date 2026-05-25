<?php

declare(strict_types=1);

namespace App\Contracts\Storage;

use App\Core\Entity\Permit;

/**
 * Interface für Datenhaltungs- und Persistenz-Engines.
 *
 * Definiert CRUD-Operationen für Genehmigungs-Entitäten, performante Indizes (Hash/Kennzeichen)
 * sowie Migrations-Hilfsmittel für den Wechsel zwischen Speicher-Backends.
 * Kontext: Data Access Layer (DAL) Abstraktion (z.B. für MySQL oder JSON-Datei-Speicherung).
 *
 * Definiert die Verträge für JSON- und MySQL-Implementierungen.
 *
 * Path: src/Contracts/Storage/StorageInterface.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
interface StorageInterface
{
    /**
     * Speichert oder aktualisiert eine Genehmigungs-Entität im Storage.
     *
     * @param Permit $permit Das zu persistierende Genehmigungs-Objekt.
     *
     * @return bool True, wenn der Schreibvorgang erfolgreich abgeschlossen wurde.
     */
    public function save(Permit $permit): bool;

    /**
     * Sucht eine Genehmigung anhand ihres eindeutigen Hashes / Codes.
     *
     * @param string $hash Der Code der gesuchten Genehmigung.
     *
     * @return Permit|null Die gefundene Entität oder null bei Nichtexistenz.
     */
    public function findByHash(string $hash): ?Permit;

    /**
     * Sucht eine aktive Genehmigung über das amtliche Fahrzeugkennzeichen.
     *
     * @param string $plate Das bereinigte Kennzeichen als Suchstring.
     *
     * @return Permit|null Das passende Objekt oder null, falls kein Eintrag existiert.
     */
    public function findByLicensePlate(string $plate): ?Permit;

    /**
     * Lädt alle im Speicher abgelegten Genehmigungen ohne Einschränkung.
     *
     * @return array<int, Permit> Liste aller Genehmigungs-Entitäten.
     */
    public function getAll(): array;

    /**
     * Migriert alle enthaltenen Datensätze dieses Storages in ein anderes Ziel-Storage.
     *
     * @param StorageInterface $target Das Ziel-Repository, in welches die Daten geschrieben werden.
     *
     * @return int Die Anzahl der erfolgreich übertragenen Datensätze.
     */
    public function migrateTo(StorageInterface $target): int;

    /**
     * Überführt ein primitives, assoziatives Rohdaten-Array in ein stark typisiertes Permit-Objekt.
     *
     * @param array<string, mixed> $item Zeilen-Rohdaten aus der DB oder JSON-Datei.
     *
     * @return Permit Das hydrierte und einsatzbereite Entitäten-Objekt.
     */
    public function mapToEntity(array $item): Permit;
}
