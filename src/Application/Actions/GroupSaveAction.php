<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\GroupSaveRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Application\RequiresPermissionInterface;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Core\Entity\Group;
use App\Core\Service\AuthService;
use App\Infrastructure\Storage\ImageStorageService;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class GroupSaveAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuthService $auth,
        private GroupRepositoryInterface $groupRepository,
        private ImageStorageService $imageStorage,
        private SessionManager $sessionManager,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'system.permissions.groups.manage';
    }

    /**
     * Erstellt eine neue Benutzergruppe oder aktualisiert bestehende Rechte-Zuordnungen.
     * Aktualisiert die Session-Rechte zur Laufzeit, falls die eigene Gruppe modifiziert wurde.
     */
    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = GroupSaveRequest::fromArray($request->post, $request->files);
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users.php');
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
            $this->imageStorage->uploadImage('group_images', $groupId, $dto->groupIcon);
        }

        if ($isUpdate) {
            if ($this->auth->getGroup() === $groupId) {
                $this->auth->refreshSessionPermissions($groupId);
            }
            $this->sessionManager->addFlash('success', "Rechte für Gruppe '{$dto->groupName}' erfolgreich aktualisiert.");

            return new RedirectResponse('users.php');
        }

        $this->sessionManager->addFlash('success', "Neue Gruppe '{$dto->groupName}' wurde erstellt.");

        return new RedirectResponse('users.php');
    }
}
