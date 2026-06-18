<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\SimpleIdentifierRequest;
use App\Application\Exception\ValidationException;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Service\AuthService;

/**
 * Action zum Löschen eines Benutzers.
 *
 * Path: src/Application/Actions/UserDeleteAction.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class UserDeleteAction implements ActionInterface
{
    public function __construct(
        private AuthService $auth,
        private ConfigInterface $config,
        private UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * Löscht einen Benutzer aus dem System. Verhindert den Selbstausschluss des aktiven Admins.
     *
     * @param array<string, mixed> $post Datensatz mit der Ziel-user_id.
     *
     * @return string Erfolgs- oder Fehlermeldung.
     */
    public function execute(array $post): string
    {
        if (! $this->auth->hasPermission('system.permissions.users.manage')) {
            return 'Fehler: Keine Berechtigung.';
        }

        try {
            $dto = SimpleIdentifierRequest::fromArray($post, 'user_id');
        } catch (ValidationException $e) {
            return $e->getMessage();
        }

        if ($dto->identifier === $this->auth->getUserId()) {
            return 'Fehler: Selbstausschluss nicht möglich.';
        }

        $users = $this->userRepository->loadAll();
        if (isset($users[$dto->identifier])) {
            $name = $users[$dto->identifier]['username'] ?? $dto->identifier;
            unset($users[$dto->identifier]);
            $this->userRepository->saveAll($users);

            $avatarPath = \rtrim((string) $this->config->get('root_path'), '/\\') . '/public/assets/img/user_images/' . $dto->identifier . '.webp';
            if (\file_exists($avatarPath)) {
                @\unlink($avatarPath);
            }

            return "Benutzer '$name' wurde entfernt.";
        }

        return 'Fehler: Benutzer nicht gefunden.';
    }
}
