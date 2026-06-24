<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\ProfileUpdatePasswordRequest;
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
 * Action zum Ändern des eigenen Passworts (inkl. Alt-Passwort-Prüfung).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class ProfileUpdatePasswordAction implements ActionInterface
{
    public function __construct(
        private AuthService $auth,
        private SessionManager $sessionManager, // HINZUGEFÜGT
        private UserRepositoryInterface $userRepository,
        private UserService $userService,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = ProfileUpdatePasswordRequest::fromArray($request->post);
        } catch (ValidationException $e) {
            return new RedirectResponse('profile.php?msg=' . \urlencode($e->getMessage()));
        }
        $userId = $this->auth->getUserId();

        try {
            $this->userService->verifyOldPassword($userId, $dto->oldPassword);
            $users = $this->userRepository->loadAll();
            if (isset($users[$userId])) {
                $u              = $users[$userId];
                $newHash        = \password_hash($dto->newPassword, \PASSWORD_DEFAULT);
                $users[$userId] = new User($u->id, $u->username, $u->groupId, $newHash);
                $this->userRepository->saveAll($users);
                $this->sessionManager->setAuthSession($userId, $u->groupId, $u->username, $newHash);

                return new RedirectResponse('profile.php?msg=' . \urlencode('Erfolg: Ihr Passwort wurde geändert.'));
            }

            return new RedirectResponse('profile.php?msg=' . \urlencode('Fehler: Benutzer nicht gefunden.'));
        } catch (\DomainException $e) {
            return new RedirectResponse('profile.php?msg=' . \urlencode($e->getMessage()));
        }
    }
}
