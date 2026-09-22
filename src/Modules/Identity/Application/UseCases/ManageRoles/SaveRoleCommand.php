<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageRoles;

final readonly class SaveRoleCommand
{
    public function __construct(
        public string $roleId,
        public string $roleName,
        public string $inheritRoleId,
        public array $permissions,
    ) {
    }
}
