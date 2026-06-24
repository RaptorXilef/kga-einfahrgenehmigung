<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\UserChangeGroupRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Application\RequiresPermissionInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Entity\User;

/**
 * Action zum Ändern der Berechtigungsgruppe eines Benutzers.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class UserChangeGroupAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
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
            return new RedirectResponse('users.php?msg=' . \urlencode($e->getMessage()));
        }
        $users = $this->userRepository->loadAll();
        if (isset($users[$dto->userId])) {
            $u                   = $users[$dto->userId];
            $users[$dto->userId] = new User($u->id, $u->username, $dto->group, $u->passwordHash);
            $this->userRepository->saveAll($users);

            return new RedirectResponse('users.php?msg=' . \urlencode("Gruppe für '{$u->username}' geändert."));
        }

        return new RedirectResponse('users.php?msg=' . \urlencode('Fehler: Benutzer nicht gefunden.'));
    }
}
