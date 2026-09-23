<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageUsers;

use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserRepositoryInterface;
use App\SharedKernel\Application\Command\CommandHandlerInterface;
use DomainException;

/**
 * @implements CommandHandlerInterface<ChangeUserRoleCommand>
 */
final readonly class ChangeUserRoleHandler implements CommandHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $repository,
    ) {
    }

    public function handle(mixed $command): void
    {
        $user = $this->repository->findById($command->userId);
        if (!$user instanceof User) {
            throw new DomainException('Fehler: Benutzer nicht gefunden.');
        }

        $user->changeRole($command->newRoleId);
        $this->repository->save($user);
    }
}
