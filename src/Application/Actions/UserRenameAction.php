<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Contracts\Application\ActionInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Service\AuthService;

/**
 * TODO DOCBLOCK
 *
 * Path: src/Application/Actions/UserRenameAction.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class UserRenameAction implements ActionInterface
{
    public function __construct(private AuthService $auth, private UserRepositoryInterface $userRepository)
    {
    }

    /**
     * Benennt den Login-Namen eines existierenden Benutzers um.
     *
     * @param array<string, mixed> $post Datensatz mit user_id und new_username.
     *
     * @return string Erfolgs- oder Fehlermeldung.
     */
    public function execute(array $post): string
    {
        if (! $this->auth->hasPermission('system.permissions.users.manage')) {
            return 'Fehler: Keine Berechtigung.';
        }
        $userId   = (string) ($post['user_id'] ?? '');
        $newLogin = \trim((string) ($post['new_username'] ?? ''));
        $users    = $this->userRepository->loadAll();

        foreach ($users as $id => $userData) {
            if ($id !== $userId && \strtolower(\trim((string) ($userData['username'] ?? ''))) === \strtolower($newLogin)) {
                return "Fehler: Ein Benutzer mit dem Namen '$newLogin' existiert bereits.";
            }
        }

        if (isset($users[$userId])) {
            $users[$userId]['username'] = $newLogin;
            $this->userRepository->saveAll($users);

            return 'Login-Name aktualisiert.';
        }

        return 'Fehler: Benutzer nicht gefunden.';
    }
}
