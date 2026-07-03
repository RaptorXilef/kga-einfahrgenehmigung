<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\DTO\GroupRenameRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Application\RequiresPermissionInterface;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Core\Entity\Group;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('rename_group')]
final readonly class GroupRenameAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private GroupRepositoryInterface $groupRepository,
        private SessionManager $sessionManager,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'system.permissions.groups.manage';
    }

    /**
     * Ändert den Anzeigenamen einer spezifischen Gruppe im System.
     */
    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = GroupRenameRequest::fromArray($request->post);
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users.php');
        }

        $groups = $this->groupRepository->loadAll();
        if (! isset($groups[$dto->groupId])) {
            $this->sessionManager->addFlash('error', 'Fehler: Gruppe nicht gefunden.');

            return new RedirectResponse('users.php');
        }

        $g                     = $groups[$dto->groupId];
        $groups[$dto->groupId] = new Group($g->id, $dto->newGroupName, $g->permissions);
        $this->groupRepository->saveAll($groups);

        $this->sessionManager->addFlash('success', "Gruppe wurde in '{$dto->newGroupName}' umbenannt.");

        return new RedirectResponse('users.php');
    }
}
