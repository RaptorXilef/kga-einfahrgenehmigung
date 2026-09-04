<?php

declare(strict_types=1);

namespace App\Contracts\Storage;

use App\Core\Entity\Permit;

/**
 * Interface für das Speicher-Repository historischer Genehmigungen.
 * Kümmert sich um die langfristige Archivierung abgelaufener Einträge.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
interface PermitArchiveRepositoryInterface
{
    /**
     * Sucht eine archivierte Genehmigung anhand ihres eindeutigen Hashes / Codes.
     *
     * @param string $hash Der Code der gesuchten Genehmigung.
     *
     * @return Permit|null Die gefundene Entität oder null bei Nichtexistenz.
     */
    public function findByHash(string $hash): ?Permit;

    /**
     * Prüft, ob eine Genehmigung anhand ihres Codes bereits im Archiv existiert.
     *
     * @param string $code Der zu prüfende Code.
     *
     * @return bool True, wenn der Code archiviert ist.
     */
    public function isCodeInArchive(string $code): bool;

    /**
     * Verschiebt eine Liste von Genehmigungen in das Archiv des angegebenen Jahres.
     *
     * @param int $year Das Jahr der Archivierung.
     * @param array<string, mixed> $permitsToArchive Die zu archivierenden Datensätze.
     */
    public function archivePermits(int $year, array $permitsToArchive): void;

    /**
     * Anonymisiert nach DSGVO-Vorgaben alte Archiv-Einträge nach Ablauf der Aufbewahrungsfrist.
     *
     * @param int $yearsThreshold Die Aufbewahrungsfrist in Jahren (Standard: 10).
     *
     * @return int Die Anzahl der erfolgreich anonymisierten Datensätze.
     */
    public function anonymizeOldRecords(int $yearsThreshold = 10): int;

    /**
     * Lädt archivierte Genehmigungen ab einem bestimmten Jahr (Lazy-Loading)
     *
     * @return Permit[]
     */
    public function getArchivedPermits(int $minYear): array;
}
