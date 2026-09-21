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
        public readonly string $username,
        public readonly string $roleId,
        private string $passwordHash,
        private string $lastSeenChangelog,
    ) {
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

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function getLastSeenChangelog(): string
    {
        return $this->lastSeenChangelog;
    }
}
