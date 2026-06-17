<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Contracts\Application\ActionInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Service\AuthService;

/**
 * TODO DOCBLOCK
 *
 * Path: src/Application/Actions/UserUploadAvatarAction.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class UserUploadAvatarAction implements ActionInterface
{
    public function __construct(private AuthService $auth, private UserRepositoryInterface $userRepository)
    {
    }

    /**
     * Verarbeitet den Upload und die Skalierung/Speicherung eines Benutzer-Profilbildes.
     *
     * @param array<string, mixed> $post Das Post-Array mit der Ziel-User-ID.
     *
     * @return string UI-Meldungstext.
     */
    public function execute(array $post): string
    {
        if (! $this->auth->hasPermission('system.permissions.users.manage')) {
            return 'Fehler: Keine Berechtigung.';
        }
        $file = $_FILES['avatar'] ?? null;
        if (! $file || $file['error'] !== 0) {
            return 'Fehler beim Upload.';
        }

        $userId = (string) ($post['user_id'] ?? '');

        return $this->userRepository->uploadImage($userId, $file) ? 'Profilbild aktualisiert.' : 'Fehler beim Verarbeiten.';
    }
}
