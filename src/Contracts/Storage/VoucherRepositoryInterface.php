<?php

declare(strict_types=1);

namespace App\Contracts\Storage;

/**
 * Interface für das Speicher-Repository von Gutscheinen.
 * Verwaltet aktive Gutscheincodes sowie das Historien-Archiv bereits eingelöster Codes.
 *
 * Path: src/Contracts/Storage/VoucherRepositoryInterface.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
interface VoucherRepositoryInterface
{
    /**
     * Lädt alle aktiven Gutscheine.
     *
     * @return array<string, array<string, mixed>> Aktive Gutscheine.
     */
    public function loadAll(): array;

    /**
     * Speichert die aktiven Gutscheine.
     *
     * @param array<string, array<string, mixed>> $vouchers Die Gutscheindaten.
     * @param bool                                $forceSql Erzwingt das Speichern in MySQL.
     */
    public function saveAll(array $vouchers, bool $forceSql = false): void;

    /**
     * Lädt das Archiv bereits eingelöster Gutscheine.
     *
     * @return array<int|string, array<string, mixed>> Die archivierten Gutscheine.
     */
    public function loadArchive(): array;

    /**
     * Fügt einen eingelösten Gutschein dem Archiv hinzu.
     *
     * @param array<string, mixed> $archiveEntry Der hinzuzufügende Datensatz.
     */
    public function appendToArchive(array $archiveEntry): void;
}
