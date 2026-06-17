<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Contracts\Application\ActionInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Service\AuthService;

/**
 * TODO DOCBLOCK
 *
 * Path: src/Application/Actions/UserSaveAction.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class UserSaveAction implements ActionInterface
{
    public function __construct(private AuthService $auth, private UserRepositoryInterface $userRepository)
    {
    }

    /**
     * Erstellt einen neuen Datensatz in der Benutzerverwaltung inklusive Passwort-Hashing.
     *
     * @param array<string, mixed> $post Formulardaten (username, password, group).
     *
     * @return string Status- oder Fehlermeldung für die UI.
     */
    public function execute(array $post): string
    {
        if (! $this->auth->hasPermission('system.permissions.users.manage')) {
            return 'Fehler: Keine Berechtigung für die Benutzerverwaltung.';
        }

        $loginName = \trim((string) ($post['username'] ?? ''));
        $pw1       = (string) ($post['password'] ?? '');
        $pw2       = (string) ($post['password_repeat'] ?? '');

        if ($pw1 !== $pw2) {
            return 'Fehler: Passwörter stimmen nicht überein.';
        }
        if ($pw1 === '' || $pw1 === '0') {
            return 'Fehler: Passwort darf nicht leer sein.';
        }

        $users = $this->userRepository->loadAll();
        foreach ($users as $userData) {
            if (\strtolower(\trim((string) ($userData['username'] ?? ''))) === \strtolower($loginName)) {
                return "Fehler: Ein Benutzer mit dem Namen '$loginName' existiert bereits im System.";
            }
        }

        do {
            $newId = $this->auth->generateId('usr_');
        } while (isset($users[$newId]));

        $users[$newId] = [
            'username' => $loginName,
            'group'    => $post['group'] ?? 'guest',
            'pass'     => \password_hash($pw1, \PASSWORD_DEFAULT),
        ];

        $this->userRepository->saveAll($users);

        $file = $_FILES['avatar'] ?? null;
        if ($file && $file['error'] === 0) {
            $this->userRepository->uploadImage($newId, $file);
        }

        return "Benutzer '$loginName' erfolgreich erstellt.";
    }
}
