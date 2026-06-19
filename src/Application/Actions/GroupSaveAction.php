<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\GroupSaveRequest;
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
final readonly class GroupSaveAction implements ActionInterface
{
    public function __construct(
        private AuthService $auth,
        private GroupRepositoryInterface $groupRepository,
    ) {
    }

    /**
     * Erstellt eine neue Benutzergruppe oder aktualisiert bestehende Rechte-Zuordnungen.
     * Aktualisiert die Session-Rechte zur Laufzeit, falls die eigene Gruppe modifiziert wurde.
     */
    public function execute(array $post): mixed
    {
        if (! $this->auth->hasPermission('system.permissions.groups.manage')) {
            return 'Fehler: Keine Berechtigung.';
        }

        try {
            $dto = GroupSaveRequest::fromArray($post, $_FILES);
        } catch (ValidationException $e) {
            return $e->getMessage();
        }
        $groups   = $this->groupRepository->loadAll();
        $isUpdate = $dto->groupId !== '' && isset($groups[$dto->groupId]);
        $groupId  = $dto->groupId;
        if (! $isUpdate) {
            do {
                $groupId = $this->auth->generateId('grp_');
            } while (isset($groups[$groupId]));
        }
        $newPermissions = $dto->permissions;
        if (! $isUpdate && $dto->inheritGroup !== '' && isset($groups[$dto->inheritGroup])) {
            $newPermissions = $groups[$dto->inheritGroup]->permissions;
        }
        $groups[$groupId] = new Group($groupId, $dto->groupName, $newPermissions);
        $this->groupRepository->saveAll($groups);
        if ($dto->groupIcon !== null) {
            $this->groupRepository->uploadImage($groupId, $dto->groupIcon);
        }
        if ($isUpdate) {
            if ($this->auth->getGroup() === $groupId) {
                $this->auth->refreshSessionPermissions($groupId);
            }

            return "Rechte für Gruppe '{$dto->groupName}' erfolgreich aktualisiert.";
        }

        return "Neue Gruppe '{$dto->groupName}' wurde erstellt.";
    }
}
