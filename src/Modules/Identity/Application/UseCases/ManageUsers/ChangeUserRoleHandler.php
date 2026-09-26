<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageUsers;

use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Domain\RoleRepositoryInterface;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserRepositoryInterface;
use App\SharedKernel\Application\Command\CommandInterface;
use App\SharedKernel\Application\Command\CommandWithResultHandlerInterface;
use DomainException;
use Override;

/**
 * @implements CommandWithResultHandlerInterface<ChangeUserRoleCommand, ChangeUserRoleResult>
 */
final readonly class ChangeUserRoleHandler implements CommandWithResultHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $repository,
        private RoleRepositoryInterface $roleRepository,
    ) {
    }

    /**
     * @param ChangeUserRoleCommand $command
     */
    #[Override]
    public function handle(CommandInterface $command): ChangeUserRoleResult
    {
        $user = $this->repository->findById($command->userId);
        if (!$user instanceof User) {
            throw new DomainException('Fehler: Benutzer nicht gefunden.');
        }

        $oldRoleId = $user->getRoleId();
        $username = $user->getUsername();

        $user->changeRole($command->newRoleId);
        $this->repository->save($user);

        $oldRole = $this->roleRepository->findById($oldRoleId);
        $newRole = $this->roleRepository->findById($command->newRoleId);

        $oldRoleName = $oldRole instanceof Role ? $oldRole->getName() : $oldRoleId;
        $newRoleName = $newRole instanceof Role ? $newRole->getName() : $command->newRoleId;

        return new ChangeUserRoleResult(
            newRoleName: $newRoleName,
            oldRoleName: $oldRoleName,
            username: $username,
        );
    }
}
