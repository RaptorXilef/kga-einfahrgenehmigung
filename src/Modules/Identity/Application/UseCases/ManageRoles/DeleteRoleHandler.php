<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageRoles;

use App\Contracts\Event\EventDispatcherInterface;
use App\Contracts\Utils\ClockInterface;
use App\Modules\Identity\Domain\Events\RoleDeletedEvent;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Domain\RoleRepositoryInterface;
use App\SharedKernel\Application\Command\CommandInterface;
use App\SharedKernel\Application\Command\CommandWithResultHandlerInterface;
use DomainException;
use Override;

/**
 * @implements CommandWithResultHandlerInterface<DeleteRoleCommand, string>
 */
final readonly class DeleteRoleHandler implements CommandWithResultHandlerInterface
{
    public function __construct(
        private RoleRepositoryInterface $repository,
        private EventDispatcherInterface $eventDispatcher,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param DeleteRoleCommand $command
     */
    #[Override]
    public function handle(CommandInterface $command): string
    {
        if ($command->roleId === 'admin') {
            throw new DomainException('Die Admin-Rolle kann nicht gelöscht werden.');
        }

        $role = $this->repository->findById($command->roleId);
        if (!$role instanceof Role) {
            throw new DomainException('Rolle nicht gefunden.');
        }

        $this->repository->delete($command->roleId);

        // Neues VSA-Event triggern, damit z.B. Icons gelöscht werden
        $this->eventDispatcher->dispatch(new RoleDeletedEvent($command->roleId, $this->clock->now()));

        return $role->getName();
    }
}
