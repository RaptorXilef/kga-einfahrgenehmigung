<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\UserResetPasswordRequest;
use App\Application\Exception\ValidationException;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Entity\User;
use App\Core\Service\AuthService;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class UserResetPasswordAction implements ActionInterface
{
    public function __construct(
        private AuthService $auth,
        private UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * Setzt das Passwort eines Benutzers administrativ (ohne Alt-Passwort-Prüfung) zurück.
     */
    public function execute(array $post): mixed
    {
        if (! $this->auth->hasPermission('system.permissions.users.manage')) {
            return 'Fehler: Keine Berechtigung.';
        }

        try {
            $dto = UserResetPasswordRequest::fromArray($post);
        } catch (ValidationException $e) {
            return $e->getMessage();
        }
        $users = $this->userRepository->loadAll();
        if (isset($users[$dto->userId])) {
            $u                   = $users[$dto->userId];
            $users[$dto->userId] = new User($u->id, $u->username, $u->groupId, \password_hash($dto->newPassword, \PASSWORD_DEFAULT));
            $this->userRepository->saveAll($users);

            return 'Passwort wurde zurückgesetzt.';
        }

        return 'Fehler: Benutzer nicht gefunden.';
    }
}
