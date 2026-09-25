<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageUsers;

use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserRepositoryInterface;
use App\Modules\Identity\Domain\UserUniquenessChecker;
use App\SharedKernel\Application\Command\CommandInterface;
use App\SharedKernel\Application\Command\CommandWithResultHandlerInterface;
use DomainException;
use Override;

/**
 * @implements CommandWithResultHandlerInterface<RenameUserCommand, string>
 */
final readonly class RenameUserHandler implements CommandWithResultHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $repository,
        private UserUniquenessChecker $uniquenessChecker,
    ) {
    }

    /**
     * Gibt den alten Namen für das Audit-Log zurück.
     *
     * @param RenameUserCommand $command
     */
    #[Override]
    public function handle(CommandInterface $command): string
    {
        $user = $this->repository->findById($command->userId);
        if (!$user instanceof User) {
            throw new DomainException('Fehler: Benutzer nicht gefunden.');
        }

        $this->uniquenessChecker->check($command->newUsername, $user->id);

        $oldName = $user->getUsername();
        $user->rename($command->newUsername);
        $this->repository->save($user);

        return $oldName;
    }
}
