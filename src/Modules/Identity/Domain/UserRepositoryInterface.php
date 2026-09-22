<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

/**
 * Vertrag für das Laden und Speichern von Benutzern.
 */
interface UserRepositoryInterface
{
    /**
     * @return array<string, User> Alle Benutzer indiziert nach ID.
     */
    public function loadAll(): array;

    public function findById(string $id): ?User;

    public function findByUsername(string $username): ?User;

    public function save(User $user): void;

    public function delete(string $id): void;
}
