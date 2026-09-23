<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use DomainException;

/**
 * Domain Service zur Sicherstellung, dass Benutzernamen systemweit eindeutig sind.
 */
final readonly class UserUniquenessChecker
{
    public function __construct(
        private UserRepositoryInterface $repository,
    ) {
    }

    public function check(string $username, ?string $excludeUserId = null): void
    {
        $existing = $this->repository->findByUsername($username);

        if ($existing instanceof User && $existing->id !== $excludeUserId) {
            throw new DomainException("Fehler: Ein Benutzer mit dem Namen '{$username}' existiert bereits.");
        }
    }
}
