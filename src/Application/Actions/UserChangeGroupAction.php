<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\UserChangeGroupRequest;
use App\Application\Exception\ValidationException;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Entity\User;
use App\Core\Service\AuthService;

/**
 * Action zum Ändern der Berechtigungsgruppe eines Benutzers.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class UserChangeGroupAction implements ActionInterface
{
    public function __construct(private AuthService $auth, private UserRepositoryInterface $userRepository)
    {
    }

    /**
     * Weist einem Benutzer eine neue Berechtigungsgruppe/Rolle zu.
     */
    public function execute(array $post): mixed
    {
        if (! $this->auth->hasPermission('system.permissions.users.manage')) {
            return 'Fehler: Keine Berechtigung.';
        }

        try {
            $dto = UserChangeGroupRequest::fromArray($post);
        } catch (ValidationException $e) {
            return $e->getMessage();
        }
        $users = $this->userRepository->loadAll();
        if (isset($users[$dto->userId])) {
            $u                   = $users[$dto->userId];
            $users[$dto->userId] = new User($u->id, $u->username, $dto->group, $u->passwordHash);
            $this->userRepository->saveAll($users);

            return "Gruppe für '{$u->username}' geändert.";
        }

        return 'Fehler: Benutzer nicht gefunden.';
    }
}
