<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageRoles;

final readonly class DeleteRoleCommand
{
    public function __construct(public string $roleId)
    {
    }
}
