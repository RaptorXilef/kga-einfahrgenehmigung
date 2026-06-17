<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Contracts\Application\ActionInterface;
use App\Contracts\Storage\UserRepositoryInterface;

/**
 * TODO DOCBLOCK
 *
 * Path: src/Application/Actions/ProfileUploadAvatarAction.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class ProfileUploadAvatarAction implements ActionInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    // TODO DOCBLOCK
    public function execute(array $post): string
    {
        $file = $_FILES['avatar'] ?? null;
        if (! $file || $file['error'] !== 0) {
            return 'Fehler beim Upload.';
        }

        $userId = $_SESSION['user_id'] ?? '';
        if ($this->userRepository->uploadImage($userId, $file)) {
            return 'Erfolg: Profilbild wurde aktualisiert.';
        }

        return 'Fehler bei der Bildverarbeitung.';
    }
}
