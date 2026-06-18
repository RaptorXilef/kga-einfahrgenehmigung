<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\UserSaveRequest;
use App\Application\Exception\ValidationException;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Service\AuthService;

/**
 * Action zum Erstellen eines neuen Benutzers.
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
    public function __construct(
        private AuthService $auth,
        private UserRepositoryInterface $userRepository,
    ) {
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

        try {
            // 1. DTO bauen (inkl. automatischer Validierung!)
            // Übergabe von $_FILES an den DTO-Validator
            $dto = UserSaveRequest::fromArray($post, $_FILES);
        } catch (ValidationException $e) {
            // Wenn die Passwörter nicht stimmen, fangen wir das hier elegant ab.
            return $e->getMessage();
        }

        // 2. Business-Logik (Die Daten im DTO sind garantiert sicher und typisiert)
        $users = $this->userRepository->loadAll();

        // Eindeutigkeit prüfen (kann das DTO nicht machen, da es die DB nicht kennen soll)
        foreach ($users as $userData) {
            if (\strtolower(\trim((string) ($userData['username'] ?? ''))) === \strtolower($dto->username)) {
                return "Fehler: Ein Benutzer mit dem Namen '{$dto->username}' existiert bereits im System.";
            }
        }

        do {
            $newId = $this->auth->generateId('usr_');
        } while (isset($users[$newId]));

        // Wir nutzen jetzt die typisierten Eigenschaften des DTOs!
        $users[$newId] = [
            'username' => $dto->username,
            'group'    => $dto->group,
            'pass'     => \password_hash($dto->password, \PASSWORD_DEFAULT),
        ];

        $this->userRepository->saveAll($users);

        // Bild-Verarbeitung erfolgt über den gekapselten DTO-Zustand
        if ($dto->avatar !== null) {
            $this->userRepository->uploadImage($newId, $dto->avatar);
        }

        return "Benutzer '{$dto->username}' erfolgreich erstellt.";
    }
}
