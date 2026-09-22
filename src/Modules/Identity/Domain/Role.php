<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

final class Role
{
    public function __construct(
        public readonly string $id,
        public string $name,
        public array $permissions,
    ) {
    }

    public function rename(string $newName): void
    {
        $this->name = \trim($newName);
    }

    public function updatePermissions(array $permissions): void
    {
        $this->permissions = $permissions;
    }
}
