<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\ProfileUpdatePasswordRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
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
        private UserRepositoryInterface $userRepository,
        private UserService $userService,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = ProfileUpdatePasswordRequest::fromArray($request->post);
        } catch (ValidationException $e) {
            return $e->getMessage();
        }

        $userId = $this->auth->getUserId();

        try {
            $this->userService->verifyOldPassword($userId, $dto->oldPassword);

            $users = $this->userRepository->loadAll();
            if (isset($users[$userId])) {
                $u              = $users[$userId];
                $users[$userId] = new User(
                    $u->id,
                    $u->username,
                    $u->groupId,
                    \password_hash($dto->newPassword, \PASSWORD_DEFAULT),
                );
                $this->userRepository->saveAll($users);

                return 'Erfolg: Ihr Passwort wurde geändert.';
            }

            return 'Fehler: Benutzer nicht gefunden.';
        } catch (\DomainException $e) {
            return $e->getMessage();
        }
    }
}
