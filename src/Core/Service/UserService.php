<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Storage\UserRepositoryInterface;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class UserService
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function ensureUsernameIsUnique(string $username, ?string $excludeUserId = null): void
    {
        $users = $this->userRepository->loadAll();
        foreach ($users as $id => $userData) {
            if ($id !== $excludeUserId && \strtolower(\trim((string) $userData->username)) === \strtolower($username)) {
                throw new \DomainException("Fehler: Ein Benutzer mit dem Namen '{$username}' existiert bereits.");
            }
        }
    }

    public function verifyOldPassword(string $userId, string $oldPassword): void
    {
        $users = $this->userRepository->loadAll();
        if (! isset($users[$userId]) || ! \password_verify($oldPassword, (string) $users[$userId]->passwordHash)) {
            throw new \DomainException('Fehler: Das aktuelle Passwort ist nicht korrekt.');
        }
    }

    public function ensureNoSelfExclusion(string $targetUserId, string $currentUserId): void
    {
        if ($targetUserId === $currentUserId) {
            throw new \DomainException('Fehler: Selbstausschluss nicht möglich.');
        }
    }
}
