<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageUsers;

use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserRepositoryInterface;
use DomainException;

final readonly class ChangeUserPasswordHandler
{
    public function __construct(
        private UserRepositoryInterface $repository,
    ) {
    }

    /**
     * Gibt den neuen Hash für das Session-Update zurück.
     */
    public function handle(ChangeUserPasswordCommand $command): string
    {
        $user = $this->repository->findById($command->userId);
        if (!$user instanceof User) {
            throw new DomainException('Fehler: Benutzer nicht gefunden.');
        }

        if ($command->oldPassword !== null && !$user->verifyPassword($command->oldPassword)) {
            throw new DomainException('Fehler: Das aktuelle Passwort ist nicht korrekt.');
        }

        $user->changePassword($command->newPassword);
        $this->repository->save($user);

        return $user->getPasswordHash();
    }
}
