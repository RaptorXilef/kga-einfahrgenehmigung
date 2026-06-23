<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\ProfileUpdateUsernameRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Session\SessionManager;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Entity\User;
use App\Core\Service\AuthService;
use App\Core\Service\UserService;

/**
 * Action zum Aktualisieren des eigenen Anzeigenamens/Login-Namens.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class ProfileUpdateUsernameAction implements ActionInterface
{
    public function __construct(
        private AuthService $auth,
        private SessionManager $sessionManager,
        private UserRepositoryInterface $userRepository,
        private UserService $userService,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = ProfileUpdateUsernameRequest::fromArray($request->post);
        } catch (ValidationException $e) {
            return $e->getMessage();
        }

        $userId = $this->auth->getUserId();

        try {
            $this->userService->ensureUsernameIsUnique($dto->newUsername, $userId);

            $users = $this->userRepository->loadAll();
            if (isset($users[$userId])) {
                $u              = $users[$userId];
                $users[$userId] = new User(
                    $u->id,
                    $dto->newUsername,
                    $u->groupId,
                    $u->passwordHash,
                );
                $this->userRepository->saveAll($users);
                $this->sessionManager->updateAdminUsername($dto->newUsername);

                return 'Erfolg: Ihr Anzeigename wurde aktualisiert.';
            }

            return 'Fehler: Benutzer nicht gefunden.';
        } catch (\DomainException $e) {
            return $e->getMessage();
        }
    }
}
