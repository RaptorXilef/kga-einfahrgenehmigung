<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageRoles;

use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Domain\RoleRepositoryInterface;
use DomainException;

final readonly class RenameRoleHandler
{
    public function __construct(private RoleRepositoryInterface $repository)
    {
    }

    public function handle(RenameRoleCommand $command): string
    {
        $role = $this->repository->findById($command->roleId);
        if (!$role instanceof Role) {
            throw new DomainException('Rolle nicht gefunden.');
        }

        $oldName = $role->name;
        $role->rename($command->newRoleName);
        $this->repository->save($role);

        return $oldName;
    }
}
