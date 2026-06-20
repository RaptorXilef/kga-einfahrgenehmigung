<?php

declare(strict_types=1);

namespace App\Contracts\Storage;

use App\Core\Entity\User;

/**
 * Interface für das Speicher-Repository der Administratoren/Benutzer.
 * Definiert CRUD- und Profilbild-Operationen für Systemnutzer.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
interface UserRepositoryInterface
{
    /**
     * Lädt alle Administratoren/Benutzer.
     *
     * @return User[]
     */
    public function loadAll(): array;

    /**
     * Speichert alle Benutzer.
     *
     * @param array<string, array<string, mixed>> $users    Die zu speichernden Benutzer.
     * @param bool                                $forceSql Erzwingt das Speichern in MySQL (ignoriert JSON).
     *
     * @param User[] $users
     */
    public function saveAll(array $users, bool $forceSql = false): void;
}
