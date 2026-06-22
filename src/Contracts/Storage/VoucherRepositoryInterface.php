<?php

declare(strict_types=1);

namespace App\Contracts\Storage;

use App\Core\Entity\Voucher;

/**
 * Interface für das Speicher-Repository von Gutscheinen.
 * Verwaltet aktive Gutscheincodes sowie das Historien-Archiv bereits eingelöster Codes.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
interface VoucherRepositoryInterface
{
    /**
     * Lädt alle aktiven Gutscheine.
     *
     * @return Voucher[]
     */
    public function loadAll(): array;

    /**
     * Speichert die aktiven Gutscheine.
     *
     * @param Voucher[] $vouchers
     * @param bool      $forceSql Erzwingt das Speichern in MySQL.
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

    public function import(array $data): void;

    public function importArchive(array $data): void;
}
