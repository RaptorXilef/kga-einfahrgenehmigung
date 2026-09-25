<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageUsers;

use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserRepositoryInterface;
use App\Modules\Identity\Domain\UserUniquenessChecker;
use App\SharedKernel\Application\Command\CommandWithResultHandlerInterface;
use Override;

/**
 * Da wir die neue ID zurückgeben müssen (für den Avatar-Upload), lösen wir
 * uns hier pragmatisch vom void CommandHandlerInterface.
 *
 * @implements CommandWithResultHandlerInterface<CreateUserCommand, string>
 */
final readonly class CreateUserHandler implements CommandWithResultHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $repository,
        private UserUniquenessChecker $uniquenessChecker,
    ) {
    }

    /**
     * @param CreateUserCommand $command
     */
    #[Override]
    public function handle(mixed $command): string
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

        return $newId;
    }
}
