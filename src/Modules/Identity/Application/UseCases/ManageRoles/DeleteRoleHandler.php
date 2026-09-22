<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageRoles;

use App\Contracts\Event\EventDispatcherInterface;
use App\Modules\Identity\Domain\Events\RoleDeletedEvent;
use App\Modules\Identity\Domain\RoleRepositoryInterface;
use DomainException;

final readonly class DeleteRoleHandler
{
    public function __construct(
        private RoleRepositoryInterface $repository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function handle(DeleteRoleCommand $command): string
    {
        if ($command->roleId === 'admin') {
            throw new DomainException('Die Admin-Rolle kann nicht gelöscht werden.');
        }

        $role = $this->repository->findById($command->roleId);
        if ($role === null) {
            throw new DomainException('Rolle nicht gefunden.');
        }

        $this->repository->delete($command->roleId);

        // Neues VSA-Event triggern, damit z.B. Icons gelöscht werden
        $this->eventDispatcher->dispatch(new RoleDeletedEvent($command->roleId));

        return $role->name;
    }
}
