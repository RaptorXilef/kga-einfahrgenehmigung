<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Contracts\Application\ActionInterface;
use App\Contracts\Storage\UserRepositoryInterface;

/**
 * TODO DOCBLOCK
 *
 * Path: src/Application/Actions/ProfileUpdatePasswordAction.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class ProfileUpdatePasswordAction implements ActionInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    // TODO DOCBLOCK
    public function execute(array $post): string
    {
        $userId  = $_SESSION['user_id'] ?? '';
        $oldPass = (string) ($post['old_password'] ?? '');
        $newPass = (string) ($post['new_password'] ?? '');
        $confirm = (string) ($post['confirm_password'] ?? '');

        $users = $this->userRepository->loadAll();
        if (! isset($users[$userId]) || ! \password_verify($oldPass, (string) $users[$userId]['pass'])) {
            return 'Fehler: Das aktuelle Passwort ist nicht korrekt.';
        }
        if ($newPass !== $confirm) {
            return 'Fehler: Die Passwort-Bestätigung stimmt nicht überein.';
        }
        if (\strlen($newPass) < 8) {
            return 'Fehler: Das neue Passwort ist zu kurz.';
        }

        $users[$userId]['pass'] = \password_hash($newPass, \PASSWORD_DEFAULT);
        $this->userRepository->saveAll($users);

        return 'Erfolg: Ihr Passwort wurde geändert.';
    }
}
