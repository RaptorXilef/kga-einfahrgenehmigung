<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

interface RoleRepositoryInterface
{
    /**
     * @return array<string, Role>
     */
    public function loadAll(): array;

    public function findById(string $id): ?Role;

    public function save(Role $role): void;

    public function delete(string $id): void;
}
