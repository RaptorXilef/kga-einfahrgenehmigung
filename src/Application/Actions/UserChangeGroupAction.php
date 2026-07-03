<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\DTO\UserChangeGroupRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Application\RequiresPermissionInterface;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Entity\User;
use App\Core\Service\AuditLoggerService;

/**
 * Action zum Ändern der Berechtigungsgruppe eines Benutzers.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('change_user_group')]
final readonly class UserChangeGroupAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private GroupRepositoryInterface $groupRepository, // <-- NEU
        private SessionManager $sessionManager,
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'system.permissions.users.manage';
    }

    /**
     * Weist einem Benutzer eine neue Berechtigungsgruppe/Rolle zu.
     */
    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = UserChangeGroupRequest::fromArray($request->post);
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users.php');
        }

        $users = $this->userRepository->loadAll();

        if (isset($users[$dto->userId])) {
            $u        = $users[$dto->userId];
            $oldGroup = $u->groupId;

            $users[$dto->userId] = new User($u->id, $u->username, $dto->group, $u->passwordHash);
            $this->userRepository->saveAll($users);

            // Gruppen-Namen für das Log auflösen
            $groups       = $this->groupRepository->loadAll();
            $oldGroupName = isset($groups[$oldGroup]) ? $groups[$oldGroup]->name : $oldGroup;
            $newGroupName = isset($groups[$dto->group]) ? $groups[$dto->group]->name : $dto->group;

            // LOG SCHREIBEN
            $this->auditLogger->log('USER_CHANGE_GROUP', "Rolle von Benutzer '{$u->username}' (ID: {$u->id}) geändert: Von '{$oldGroupName}' auf '{$newGroupName}'.");

            $this->sessionManager->addFlash('success', "Gruppe für '{$u->username}' geändert.");

            return new RedirectResponse('users.php');
        }

        $this->sessionManager->addFlash('error', 'Fehler: Benutzer nicht gefunden.');

        return new RedirectResponse('users.php');
    }
}
