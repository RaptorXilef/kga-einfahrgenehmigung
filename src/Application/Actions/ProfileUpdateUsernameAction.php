<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\DTO\ProfileUpdateUsernameRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
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
#[ActionRoute('change_own_username')]
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
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('profile.php');
        }

        $userId = $this->auth->getUserId();

        try {
            $this->userService->ensureUsernameIsUnique($dto->newUsername, $userId);
            $users = $this->userRepository->loadAll();

            if (isset($users[$userId])) {
                $u              = $users[$userId];
                $users[$userId] = new User($u->id, $dto->newUsername, $u->groupId, $u->passwordHash);
                $this->userRepository->saveAll($users);

                $this->sessionManager->updateAdminUsername($dto->newUsername);

                $this->sessionManager->addFlash('success', 'Erfolg: Ihr Anzeigename wurde aktualisiert.');

                return new RedirectResponse('profile.php');
            }

            $this->sessionManager->addFlash('error', 'Fehler: Benutzer nicht gefunden.');

            return new RedirectResponse('profile.php');
        } catch (\DomainException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('profile.php');
        }
    }
}
