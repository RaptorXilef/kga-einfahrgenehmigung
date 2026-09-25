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
 * @implements CommandWithResultHandlerInterface<ChangeUserPasswordCommand, ChangeUserPasswordResult>
 */
final readonly class ChangeUserPasswordHandler implements CommandWithResultHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $repository,
    ) {
    }

    /**
     * Gibt den neuen Hash für das Session-Update sowie den Benutzernamen für das Audit-Log zurück.
     *
     * @param ChangeUserPasswordCommand $command
     */
    #[Override]
    public function handle(CommandInterface $command): ChangeUserPasswordResult
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

        return new ChangeUserPasswordResult(
            passwordHash: $user->getPasswordHash(),
            username: $user->getUsername(),
        );
    }
}
