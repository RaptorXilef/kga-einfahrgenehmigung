<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Contracts\Application\ActionInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Service\AuthService;

/**
 * TODO DOCBLOCK
 *
 * Path: src/Application/Actions/UserChangeGroupAction.php
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
     *
     * @param array<string, mixed> $post Datensatz mit user_id und group.
     *
     * @return string Bestätigungstext.
     */
    public function execute(array $post): string
    {
        if (! $this->auth->hasPermission('system.permissions.users.manage')) {
            return 'Fehler: Keine Berechtigung.';
        }
        $userId = (string) ($post['user_id'] ?? '');
        $group  = (string) ($post['group'] ?? '');
        $users  = $this->userRepository->loadAll();

        if (isset($users[$userId])) {
            $users[$userId]['group'] = $group;
            $this->userRepository->saveAll($users);

            return "Gruppe für '" . ($users[$userId]['username'] ?? $userId) . "' geändert.";
        }

        return 'Fehler: Benutzer nicht gefunden.';
    }
}
