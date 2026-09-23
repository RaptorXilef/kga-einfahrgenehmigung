<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageUsers;

use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserRepositoryInterface;
use App\Modules\Identity\Domain\UserUniquenessChecker;
use DomainException;

final readonly class RenameUserHandler
{
    public function __construct(
        private UserRepositoryInterface $repository,
        private UserUniquenessChecker $uniquenessChecker,
    ) {
    }

    /**
     * Gibt den alten Namen für das Audit-Log zurück.
     */
    public function handle(RenameUserCommand $command): string
    {
        $user = $this->repository->findById($command->userId);
        if (!$user instanceof User) {
            throw new DomainException('Fehler: Benutzer nicht gefunden.');
        }

        $this->uniquenessChecker->check($command->newUsername, $user->id);

        $oldName = $user->username;
        $user->rename($command->newUsername);
        $this->repository->save($user);

        return $oldName;
    }
}
