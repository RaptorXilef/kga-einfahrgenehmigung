<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

/**
 * Vertrag für das gezielte Laden und Speichern von Benutzern (Write-Model).
 */
interface UserRepositoryInterface
{
    public function findById(string $id): ?User;

    public function findByUsername(string $username): ?User;

    public function save(User $user): void;

    public function delete(string $id): void;
}
