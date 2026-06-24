<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Event\EventDispatcherInterface;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Core\Event\GroupDeletedEvent;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class GroupService
{
    public function __construct(
        private GroupRepositoryInterface $groupRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function deleteGroup(string $groupId): void
    {
        if ($groupId === 'admin') {
            throw new \DomainException('Fehler: Die Admin-Gruppe kann nicht gelöscht werden.');
        }

        $groups = $this->groupRepository->loadAll();
        if (! isset($groups[$groupId])) {
            throw new \DomainException('Fehler: Gruppe nicht gefunden.');
        }

        unset($groups[$groupId]);
        $this->groupRepository->saveAll($groups);

        // Infrastruktur-Logik abgekoppelt:
        $this->eventDispatcher->dispatch(new GroupDeletedEvent($groupId));
    }
}
