<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageUsers;

use App\Modules\Identity\Domain\UserRepositoryInterface;
use DomainException;

final readonly class DeleteUserHandler
{
    public function __construct(
        private UserRepositoryInterface $repository,
    ) {
    }

    /**
     * Gibt den alten Namen für das Audit-Log zurück.
     */
    public function handle(DeleteUserCommand $command): string
    {
        if ($command->userId === $command->initiatorId) {
            throw new DomainException('Fehler: Selbstausschluss nicht möglich.');
        }

        $user = $this->repository->findById($command->userId);
        if ($user === null) {
            throw new DomainException('Fehler: Benutzer nicht gefunden.');
        }

        $this->repository->delete($command->userId);

        return $user->username;
    }
}
