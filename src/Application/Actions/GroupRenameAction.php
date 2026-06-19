<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\GroupRenameRequest;
use App\Application\Exception\ValidationException;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Core\Entity\Group;
use App\Core\Service\AuthService;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class GroupRenameAction implements ActionInterface
{
    public function __construct(
        private AuthService $auth,
        private GroupRepositoryInterface $groupRepository,
    ) {
    }

    /**
     * Ändert den Anzeigenamen einer spezifischen Gruppe im System.
     */
    public function execute(array $post): mixed
    {
        try {
            $dto = GroupRenameRequest::fromArray($post);
        } catch (ValidationException $e) {
            return $e->getMessage();
        }
        $groups = $this->groupRepository->loadAll();
        if (! isset($groups[$dto->groupId])) {
            return 'Fehler: Gruppe nicht gefunden.';
        }
        $g                     = $groups[$dto->groupId];
        $groups[$dto->groupId] = new Group($g->id, $dto->newGroupName, $g->permissions);
        $this->groupRepository->saveAll($groups);

        return "Gruppe wurde in '{$dto->newGroupName}' umbenannt.";
    }
}
