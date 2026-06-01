<?php

declare(strict_types=1);

namespace App\Contracts\Storage;

// TODO DocBlock
interface GroupRepositoryInterface
{
    public function loadAll(): array;

    public function saveAll(array $groups, bool $forceSql = false): void;

    public function uploadImage(string $groupId, array $file): bool;

    public function getImageUrl(string $groupId): string;
}
