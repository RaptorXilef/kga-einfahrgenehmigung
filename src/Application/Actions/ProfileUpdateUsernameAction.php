<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\ProfileUpdateUsernameRequest;
use App\Application\Exception\ValidationException;
use App\Application\Session\SessionManager;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Entity\User;
use App\Core\Service\AuthService;

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
    ) {
    }

    public function execute(array $post): mixed
    {
        try {
            $dto = ProfileUpdateUsernameRequest::fromArray($post);
        } catch (ValidationException $e) {
            return $e->getMessage();
        }
        $userId = $this->auth->getUserId();
        $users  = $this->userRepository->loadAll();
        foreach ($users as $id => $userData) {
            if ($id !== $userId && \strtolower(\trim((string) $userData->username)) === \strtolower($dto->newUsername)) {
                return "Fehler: Der Anzeigename '{$dto->newUsername}' ist bereits vergeben.";
            }
        }
        $u              = $users[$userId];
        $users[$userId] = new User($u->id, $dto->newUsername, $u->groupId, $u->passwordHash);
        $this->userRepository->saveAll($users);
        $this->sessionManager->updateAdminUsername($dto->newUsername);

        return 'Erfolg: Ihr Anzeigename wurde aktualisiert.';
    }
}
