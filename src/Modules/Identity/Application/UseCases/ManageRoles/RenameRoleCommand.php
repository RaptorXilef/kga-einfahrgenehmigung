<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageRoles;

final readonly class RenameRoleCommand
{
    public function __construct(public string $roleId, public string $newRoleName)
    {
    }
}
