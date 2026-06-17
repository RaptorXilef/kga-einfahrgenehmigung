<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Contracts\Application\ActionInterface;
use App\Contracts\Storage\UserRepositoryInterface;

/**
 * TODO DOCBLOCK
 *
 * Path: src/Application/Actions/ProfileUpdateUsernameAction.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class ProfileUpdateUsernameAction implements ActionInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    // TODO DOCBLOCK
    public function execute(array $post): string
    {
        $userId  = $_SESSION['user_id'] ?? '';
        $newName = \trim((string) ($post['new_username'] ?? ''));
        if ($newName === '') {
            return 'Fehler: Name darf nicht leer sein.';
        }

        $users = $this->userRepository->loadAll();
        foreach ($users as $id => $userData) {
            if ($id !== $userId && \strtolower(\trim((string) ($userData['username'] ?? ''))) === \strtolower($newName)) {
                return "Fehler: Der Anzeigename '$newName' ist bereits vergeben.";
            }
        }

        $users[$userId]['username'] = $newName;
        $this->userRepository->saveAll($users);
        $_SESSION['admin_user'] = $newName;

        return 'Erfolg: Ihr Anzeigename wurde aktualisiert.';
    }
}
