<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

/**
 * Vertrag für das Laden und Speichern von Benutzern.
 */
interface UserRepositoryInterface
{
    public function findByUsername(string $username): ?User;

    public function save(User $user): void;
}
