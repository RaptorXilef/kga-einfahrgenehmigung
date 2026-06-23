<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\UserRenameRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Application\RequiresPermissionInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Entity\User;
use App\Core\Service\UserService;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class UserRenameAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private UserService $userService,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'system.permissions.users.manage';
    }

    /**
     * Benennt den Login-Namen eines existierenden Benutzers um.
     */
    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = UserRenameRequest::fromArray($request->post);
        } catch (ValidationException $e) {
            return $e->getMessage();
        }

        try {
            $this->userService->ensureUsernameIsUnique($dto->newUsername, $dto->userId);

            $users = $this->userRepository->loadAll();
            if (isset($users[$dto->userId])) {
                $u                   = $users[$dto->userId];
                $users[$dto->userId] = new User(
                    $u->id,
                    $dto->newUsername,
                    $u->groupId,
                    $u->passwordHash,
                );
                $this->userRepository->saveAll($users);

                return 'Login-Name aktualisiert.';
            }

            return 'Fehler: Benutzer nicht gefunden.';
        } catch (\DomainException $e) {
            return $e->getMessage();
        }
    }
}
