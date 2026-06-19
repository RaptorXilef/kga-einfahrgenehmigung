<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\ProfileUpdatePasswordRequest;
use App\Application\Exception\ValidationException;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Entity\User;

/**
 * Action zum Ändern des eigenen Passworts (inkl. Alt-Passwort-Prüfung).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class ProfileUpdatePasswordAction implements ActionInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function execute(array $post): mixed
    {
        try {
            $dto = ProfileUpdatePasswordRequest::fromArray($post);
        } catch (ValidationException $e) {
            return $e->getMessage();
        }
        $userId = $_SESSION['user_id'] ?? '';
        $users  = $this->userRepository->loadAll();
        if (! isset($users[$userId]) || ! \password_verify(
            $dto->oldPassword,
            (string) $users[$userId]->passwordHash,
        )) {
            return 'Fehler: Das aktuelle Passwort ist nicht korrekt.';
        }
        $u              = $users[$userId];
        $users[$userId] = new User($u->id, $u->username, $u->groupId, \password_hash(
            $dto->newPassword,
            \PASSWORD_DEFAULT,
        ));
        $this->userRepository->saveAll($users);

        return 'Erfolg: Ihr Passwort wurde geändert.';
    }
}
