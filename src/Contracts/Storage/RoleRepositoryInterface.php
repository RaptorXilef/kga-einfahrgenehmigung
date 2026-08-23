<?php

declare(strict_types=1);

namespace App\Contracts\Storage;

use App\Core\Entity\Role;

interface RoleRepositoryInterface
{
    /**
     * Lädt alle Benutzerrollen.
     *
     * @return array<string, Role> Alle Rollen indiziert nach ID.
     */
    public function loadAll(): array;

    /**
     * @param array<string, Role> $roles Die zu speichernden Rollen.
     */
    public function saveAll(array $roles, bool $forceSql = false): void;
}
