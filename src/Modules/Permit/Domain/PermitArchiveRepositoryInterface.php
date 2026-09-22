<?php

declare(strict_types=1);

namespace App\Modules\Permit\Domain;

interface PermitArchiveRepositoryInterface
{
    public function findByHash(string $hash): ?Permit;

    public function isCodeInArchive(string $code): bool;

    public function archivePermits(int $year, array $permitsToArchive): void;

    public function anonymizeOldRecords(int $yearsThreshold = 10): int;

    /**
     * @return Permit[]
     */
    public function getArchivedPermits(int $minYear): array;
}
