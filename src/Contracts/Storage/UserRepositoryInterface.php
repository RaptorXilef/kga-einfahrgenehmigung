<?php

declare(strict_types=1);

namespace App\Contracts\Storage;

use App\Core\Entity\User;

/**
 * Interface für das Speicher-Repository der Administratoren/Benutzer.
 * Definiert CRUD- und Profilbild-Operationen für Systemnutzer.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
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

    /**
     * Lädt ein Profilbild für einen Benutzer hoch.
     *
     * @param string               $userId Die ID des Benutzers.
     * @param array<string, mixed> $file   Das $_FILES Array.
     *
     * @return bool True bei Erfolg.
     */
    public function uploadImage(string $userId, array $file): bool;

    /**
     * Gibt die URL zum Profilbild eines Benutzers zurück.
     *
     * @param string $userId Die ID des Benutzers.
     *
     * @return string Die vollständige Bild-URL.
     */
    public function getImageUrl(string $userId): string;
}
