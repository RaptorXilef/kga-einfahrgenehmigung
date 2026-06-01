<?php

declare(strict_types=1);

namespace App\Contracts\Storage;

// TODO DocBlock
interface UserRepositoryInterface
{
    public function loadAll(): array;

    public function saveAll(array $users, bool $forceSql = false): void;

    public function uploadImage(string $userId, array $file): bool;

    public function getImageUrl(string $userId): string;
}
