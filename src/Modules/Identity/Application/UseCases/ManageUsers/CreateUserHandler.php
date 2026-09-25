<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageUsers;

use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Domain\RoleRepositoryInterface;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserRepositoryInterface;
use App\Modules\Identity\Domain\UserUniquenessChecker;
use App\SharedKernel\Application\Command\CommandInterface;
use App\SharedKernel\Application\Command\CommandWithResultHandlerInterface;
use Override;

/**
 * Erstellt einen neuen Benutzer und gibt die generierte ID sowie den aufgelösten Rollennamen zurück.
 *
 * @implements CommandWithResultHandlerInterface<CreateUserCommand, CreateUserResult>
 */
final readonly class CreateUserHandler implements CommandWithResultHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $repository,
        private RoleRepositoryInterface $roleRepository,
        private UserUniquenessChecker $uniquenessChecker,
    ) {
    }

    /**
     * @param CreateUserCommand $command
     */
    #[Override]
    public function handle(CommandInterface $command): CreateUserResult
    {
        $this->uniquenessChecker->check($command->username);

        $newId = 'usr_' . \bin2hex(\random_bytes(8));

        $user = new User(
            id: $newId,
            username: $command->username,
            roleId: $command->roleId,
            passwordHash: \password_hash($command->password, \PASSWORD_DEFAULT),
            lastSeenChangelog: 'v0.0.0',
        );

        $this->repository->save($user);

        $role = $this->roleRepository->findById($command->roleId);
        $roleName = $role instanceof Role ? $role->name : $command->roleId;

        return new CreateUserResult(
            roleName: $roleName,
            userId: $newId,
        );
    }
}
