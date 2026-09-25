<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageRoles;

use App\SharedKernel\Application\Command\CommandInterface;

final readonly class DeleteRoleCommand implements CommandInterface
{
    public function __construct(public string $roleId)
    {
    }
}
