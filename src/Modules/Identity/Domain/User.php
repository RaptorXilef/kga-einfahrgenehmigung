<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use DomainException;

/**
 * Aggregatwurzel für einen Administrator/Benutzer im System.
 */
final class User
{
    public function __construct(
        public readonly string $id,
        private string $username,
        private string $roleId,
        private string $passwordHash,
        private string $lastSeenChangelog,
    ) {
    }

    public function rename(string $newUsername): void
    {
        if (\trim($newUsername) === '') {
            throw new DomainException('Der Benutzername darf nicht leer sein.');
        }
        $this->username = \trim($newUsername);
    }

    public function changeRole(string $newRoleId): void
    {
        if (\trim($newRoleId) === '') {
            throw new DomainException('Die Rollen-ID darf nicht leer sein.');
        }
        $this->roleId = \trim($newRoleId);
    }

    /**
     * Verifiziert ein Klartext-Passwort gegen den Hash.
     */
    public function verifyPassword(string $plainPassword): bool
    {
        return \password_verify($plainPassword, $this->passwordHash);
    }

    /**
     * Ändert das Passwort und hasht es automatisch.
     */
    public function changePassword(string $newPassword): void
    {
        if (\strlen($newPassword) < 8) {
            throw new DomainException('Das neue Passwort muss mindestens 8 Zeichen lang sein.');
        }
        $this->passwordHash = \password_hash($newPassword, \PASSWORD_DEFAULT);
    }

    public function markChangelogAsRead(string $version): void
    {
        $this->lastSeenChangelog = $version;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getRoleId(): string
    {
        return $this->roleId;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function getLastSeenChangelog(): string
    {
        return $this->lastSeenChangelog;
    }
}
