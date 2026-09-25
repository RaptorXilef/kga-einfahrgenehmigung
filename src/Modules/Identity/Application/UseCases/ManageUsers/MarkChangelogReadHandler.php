<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageUsers;

use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserRepositoryInterface;
use App\SharedKernel\Application\Command\CommandHandlerInterface;
use Override;

/**
 * Aktualisiert die gelesene Changelog-Version am User-Aggregat.
 *
 * @implements CommandHandlerInterface<MarkChangelogReadCommand>
 */
final readonly class MarkChangelogReadHandler implements CommandHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * @param MarkChangelogReadCommand $command
     */
    #[Override]
    public function handle(mixed $command): void
    {
        if (\str_starts_with($command->userId, 'sys_')) {
            return;
        }

        $user = $this->userRepository->findById($command->userId);
        if (!$user instanceof User) {
            return;
        }

        $user->markChangelogAsRead($command->version);
        $this->userRepository->save($user);
    }
}
