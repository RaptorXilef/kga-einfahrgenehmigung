<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use DomainException;

/**
 * Aggregatwurzel für eine Berechtigungs-Rolle (RBAC).
 */
final class Role
{
    /**
     * @param array<int, string> $permissions
     */
    public function __construct(
        public readonly string $id,
        private string $name,
        private array $permissions,
    ) {
        $this->rename($name);
    }

    public function rename(string $newName): void
    {
        $trimmed = \trim($newName);
        if ($trimmed === '') {
            throw new DomainException('Der Rollenname darf nicht leer sein.');
        }
        $this->name = $trimmed;
    }

    /**
     * @param array<int, string> $permissions
     */
    public function updatePermissions(array $permissions): void
    {
        $this->permissions = $permissions;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<int, string>
     */
    public function getPermissions(): array
    {
        return $this->permissions;
    }
}
