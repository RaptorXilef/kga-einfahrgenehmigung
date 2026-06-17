<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\ProfileUpdatePasswordRequest;
use App\Application\Exception\ValidationException;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Storage\UserRepositoryInterface;

/**
 * Action zum Ändern des eigenen Passworts (inkl. Alt-Passwort-Prüfung).
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
        try {
            $dto = ProfileUpdatePasswordRequest::fromArray($post);
        } catch (ValidationException $e) {
            return $e->getMessage();
        }

        $userId = $_SESSION['user_id'] ?? '';
        $users  = $this->userRepository->loadAll();

        // Die Prüfung gegen den Hash in der DB muss hier in der Action bleiben,
        // da das DTO keinen Datenbankzugriff haben soll!
        if (! isset($users[$userId]) || ! \password_verify($dto->oldPassword, (string) $users[$userId]['pass'])) {
            return 'Fehler: Das aktuelle Passwort ist nicht korrekt.';
        }

        $users[$userId]['pass'] = \password_hash($dto->newPassword, \PASSWORD_DEFAULT);
        $this->userRepository->saveAll($users);

        return 'Erfolg: Ihr Passwort wurde geändert.';
    }
}
