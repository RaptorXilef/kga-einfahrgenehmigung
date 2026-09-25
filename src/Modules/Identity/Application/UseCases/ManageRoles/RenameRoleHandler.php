<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageRoles;

use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Domain\RoleRepositoryInterface;
use App\SharedKernel\Application\Command\CommandWithResultHandlerInterface;
use DomainException;
use Override;

/**
 * @implements CommandWithResultHandlerInterface<RenameRoleCommand, string>
 */
final readonly class RenameRoleHandler implements CommandWithResultHandlerInterface
{
    public function __construct(private RoleRepositoryInterface $repository)
    {
    }

    /**
     * @param RenameRoleCommand $command
     */
    #[Override]
    public function handle(mixed $command): string
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
