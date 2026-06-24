<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\GroupRenameRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Application\RequiresPermissionInterface;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Core\Entity\Group;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class GroupRenameAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private GroupRepositoryInterface $groupRepository,
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
            return new RedirectResponse('users.php?msg=' . \urlencode($e->getMessage()));
        }
        $groups = $this->groupRepository->loadAll();
        if (! isset($groups[$dto->groupId])) {
            return new RedirectResponse('users.php?msg=' . \urlencode('Fehler: Gruppe nicht gefunden.'));
        }
        $g                     = $groups[$dto->groupId];
        $groups[$dto->groupId] = new Group($g->id, $dto->newGroupName, $g->permissions);
        $this->groupRepository->saveAll($groups);

        return new RedirectResponse('users.php?msg=' . \urlencode("Gruppe wurde in '{$dto->newGroupName}' umbenannt."));
    }
}
