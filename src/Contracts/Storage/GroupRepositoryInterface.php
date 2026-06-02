<?php

declare(strict_types=1);

namespace App\Contracts\Storage;

/**
 * Interface für das Speicher-Repository der Benutzergruppen.
 * Definiert Lese- und Schreiboperationen für Rollen, Rechte und Gruppen-Icons.
 *
 * Path: src/Contracts/Storage/GroupRepositoryInterface.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
interface GroupRepositoryInterface
{
    /**
     * Lädt alle Benutzergruppen.
     *
     * @return array<string, array<string, mixed>> Alle Gruppen indiziert nach ID.
     */
    public function loadAll(): array;

    /**
     * Speichert alle Benutzergruppen.
     *
     * @param array<string, array<string, mixed>> $groups   Die zu speichernden Gruppen.
     * @param bool                                $forceSql Erzwingt das Speichern in MySQL (ignoriert JSON).
     */
    public function saveAll(array $groups, bool $forceSql = false): void;

    /**
     * Lädt ein Gruppen-Icon hoch und konvertiert es nach WebP.
     *
     * @param string               $groupId Die ID der Gruppe.
     * @param array<string, mixed> $file    Das $_FILES Array des Uploads.
     *
     * @return bool True bei Erfolg, false bei einem Fehler.
     */
    public function uploadImage(string $groupId, array $file): bool;

    /**
     * Gibt die URL zum Gruppen-Icon zurück.
     *
     * @param string $groupId Die ID der Gruppe.
     *
     * @return string Die vollständige URL zum Bild.
     */
    public function getImageUrl(string $groupId): string;
}
