<?php

declare(strict_types=1);

namespace App\Contracts\System;

/**
 * Kapselt das Hochladen, Auslesen und Löschen von Benutzer- und Rollenbildern.
 */
interface ImageStorageInterface
{
    public function uploadImage(string $folder, string $id, array $file): bool;

    public function getImageUrl(string $folder, string $id, string $fallbackIcon): string;

    public function deleteImage(string $folder, string $id): void;
}
