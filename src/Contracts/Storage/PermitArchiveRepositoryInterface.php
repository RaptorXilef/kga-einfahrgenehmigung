<?php

declare(strict_types=1);

namespace App\Contracts\Storage;

/**
 * Interface für das Speicher-Repository historischer Genehmigungen.
 * Kümmert sich um die langfristige Archivierung abgelaufener Einträge.
 *
 * Path: src/Contracts/Storage/PermitArchiveRepositoryInterface.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
interface PermitArchiveRepositoryInterface
{
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
     * @param int                  $year             Das Jahr der Archivierung.
     * @param array<string, mixed> $permitsToArchive Die zu archivierenden Datensätze.
     */
    public function archivePermits(int $year, array $permitsToArchive): void;

    // TODO DOCBLOCK
    public function anonymizeOldRecords(int $yearsThreshold = 10): int;
}
