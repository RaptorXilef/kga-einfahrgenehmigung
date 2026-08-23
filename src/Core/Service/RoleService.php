<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Event\EventDispatcherInterface;
use App\Contracts\Storage\RoleRepositoryInterface;
use App\Core\Event\RoleDeletedEvent;
use DomainException;

final readonly class RoleService
{
    public function __construct(
        private RoleRepositoryInterface $roleRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function deleteRole(string $roleId): void
    {
        if ($roleId === 'admin') {
            throw new DomainException('Fehler: Die Admin-Rolle kann nicht gelöscht werden.');
        }

        $roles = $this->roleRepository->loadAll();
        if (!isset($roles[$roleId])) {
            throw new DomainException('Fehler: Rolle nicht gefunden.');
        }

        unset($roles[$roleId]);
        $this->roleRepository->saveAll($roles);

        $this->eventDispatcher->dispatch(new RoleDeletedEvent($roleId));
    }
}
