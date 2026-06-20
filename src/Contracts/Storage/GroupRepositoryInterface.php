<?php

declare(strict_types=1);

namespace App\Contracts\Storage;

use App\Core\Entity\Group;

/**
 * Interface für das Speicher-Repository der Benutzergruppen.
 * Definiert Lese- und Schreiboperationen für Rollen, Rechte und Gruppen-Icons.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
interface GroupRepositoryInterface
{
    /**
     * Lädt alle Benutzergruppen.
     *
     * @return Group[] Alle Gruppen indiziert nach ID.
     */
    public function loadAll(): array;

    /**
     * Speichert alle Benutzergruppen.
     *
     * @param array<string, array<string, mixed>> $groups   Die zu speichernden Gruppen.
     * @param bool                                $forceSql Erzwingt das Speichern in MySQL (ignoriert JSON).
     *
     * @param Group[] $groups
     */
    public function saveAll(array $groups, bool $forceSql = false): void;
}
