<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageUsers;

use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserRepositoryInterface;
use App\SharedKernel\Application\Command\CommandInterface;
use App\SharedKernel\Application\Command\CommandWithResultHandlerInterface;
use DomainException;
use Override;

/**
 * @implements CommandWithResultHandlerInterface<DeleteUserCommand, string>
 */
final readonly class DeleteUserHandler implements CommandWithResultHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $repository,
    ) {
    }

    /**
     * Gibt den alten Namen für das Audit-Log zurück.
     *
     * @param DeleteUserCommand $command
     */
    #[Override]
    public function handle(CommandInterface $command): string
    {
        if ($command->userId === $command->initiatorId) {
            throw new DomainException('Fehler: Selbstausschluss nicht möglich.');
        }

        $user = $this->repository->findById($command->userId);
        if (!$user instanceof User) {
            throw new DomainException('Fehler: Benutzer nicht gefunden.');
        }

        $this->repository->delete($command->userId);

        return $user->getUsername();
    }
}
