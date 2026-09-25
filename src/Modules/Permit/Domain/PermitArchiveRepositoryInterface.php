<?php

declare(strict_types=1);

namespace App\Modules\Permit\Domain;

/**
 * Vertrag für das Archivieren abgelaufener Genehmigungen und die DSGVO-Anonymisierung.
 */
interface PermitArchiveRepositoryInterface
{
    /**
     * @param Permit[] $permitsToArchive
     */
    public function archivePermits(int $year, array $permitsToArchive): void;

    public function anonymizeOldRecords(int $yearsThreshold = 10): int;
}
