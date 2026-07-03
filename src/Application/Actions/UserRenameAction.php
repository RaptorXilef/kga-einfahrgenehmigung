<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\UserRenameRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
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
        private SessionManager $sessionManager,
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
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users.php');
        }

        try {
            $this->userService->ensureUsernameIsUnique($dto->newUsername, $dto->userId);
            $users = $this->userRepository->loadAll();

            if (isset($users[$dto->userId])) {
                $u                   = $users[$dto->userId];
                $users[$dto->userId] = new User($u->id, $dto->newUsername, $u->groupId, $u->passwordHash);
                $this->userRepository->saveAll($users);

                $this->sessionManager->addFlash('success', 'Login-Name aktualisiert.');

                return new RedirectResponse('users.php');
            }

            $this->sessionManager->addFlash('error', 'Fehler: Benutzer nicht gefunden.');

            return new RedirectResponse('users.php');
        } catch (\DomainException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users.php');
        }
    }
}
