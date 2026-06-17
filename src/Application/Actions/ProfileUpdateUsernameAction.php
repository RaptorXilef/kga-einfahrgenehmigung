<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\ProfileUpdateUsernameRequest;
use App\Application\Exception\ValidationException;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Storage\UserRepositoryInterface;

/**
 * Action zum Aktualisieren des eigenen Anzeigenamens/Login-Namens.
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
        try {
            $dto = ProfileUpdateUsernameRequest::fromArray($post);
        } catch (ValidationException $e) {
            return $e->getMessage();
        }

        $userId = $_SESSION['user_id'] ?? '';
        $users  = $this->userRepository->loadAll();

        // Eindeutigkeit prüfen (Business-Logik bleibt in der Action)
        foreach ($users as $id => $userData) {
            if ($id !== $userId && \strtolower(\trim((string) ($userData['username'] ?? ''))) === \strtolower($dto->newUsername)) {
                return "Fehler: Der Anzeigename '{$dto->newUsername}' ist bereits vergeben.";
            }
        }

        $users[$userId]['username'] = $dto->newUsername;
        $this->userRepository->saveAll($users);
        $_SESSION['admin_user'] = $dto->newUsername;

        return 'Erfolg: Ihr Anzeigename wurde aktualisiert.';
    }
}
