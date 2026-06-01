<?php

declare(strict_types=1);

namespace App\Contracts\Storage;

// TODO DocBlock
interface PermitArchiveRepositoryInterface
{
    public function isCodeInArchive(string $code): bool;

    public function archivePermits(int $year, array $permitsToArchive): void;
}
