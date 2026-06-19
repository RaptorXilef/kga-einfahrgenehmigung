<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\UserRenameRequest;
use App\Application\Exception\ValidationException;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Entity\User;
use App\Core\Service\AuthService;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class UserRenameAction implements ActionInterface
{
    public function __construct(
        private AuthService $auth,
        private UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * Benennt den Login-Namen eines existierenden Benutzers um.
     */
    public function execute(array $post): mixed
    {
        if (! $this->auth->hasPermission('system.permissions.users.manage')) {
            return 'Fehler: Keine Berechtigung.';
        }

        try {
            $dto = UserRenameRequest::fromArray($post);
        } catch (ValidationException $e) {
            return $e->getMessage();
        }
        $users = $this->userRepository->loadAll();
        foreach ($users as $id => $userData) {
            if ($id !== $dto->userId && \strtolower(\trim((string) $userData->username)) === \strtolower($dto->newUsername)) {
                return "Fehler: Ein Benutzer mit dem Namen '{$dto->newUsername}' existiert bereits.";
            }
        }
        if (isset($users[$dto->userId])) {
            $u                   = $users[$dto->userId];
            $users[$dto->userId] = new User($u->id, $dto->newUsername, $u->groupId, $u->passwordHash);
            $this->userRepository->saveAll($users);

            return 'Login-Name aktualisiert.';
        }

        return 'Fehler: Benutzer nicht gefunden.';
    }
}
