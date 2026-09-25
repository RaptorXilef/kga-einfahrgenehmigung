<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageRoles;

use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Domain\RoleRepositoryInterface;
use App\SharedKernel\Application\Command\CommandInterface;
use App\SharedKernel\Application\Command\CommandWithResultHandlerInterface;
use Override;

/**
 * @implements CommandWithResultHandlerInterface<SaveRoleCommand, SaveRoleResult>
 */
final readonly class SaveRoleHandler implements CommandWithResultHandlerInterface
{
    public function __construct(private RoleRepositoryInterface $repository)
    {
    }

    /**
     * @param SaveRoleCommand $command
     */
    #[Override]
    public function handle(CommandInterface $command): SaveRoleResult
    {
        $role = $command->roleId !== '' ? $this->repository->findById($command->roleId) : null;
        $isUpdate = $role instanceof Role;
        $perms = $command->permissions;

        if (!$isUpdate) {
            $newId = $command->roleId !== '' ? $command->roleId : 'role_' . \bin2hex(\random_bytes(4));
            if ($command->inheritRoleId !== '') {
                $inherit = $this->repository->findById($command->inheritRoleId);
                if ($inherit instanceof Role) {
                    $perms = $inherit->permissions;
                }
            }
            $role = new Role($newId, $command->roleName, $perms);
        } else {
            $role->rename($command->roleName);
            $role->updatePermissions($perms);
        }

        $this->repository->save($role);

        return new SaveRoleResult($role->id, $isUpdate);
    }
}
