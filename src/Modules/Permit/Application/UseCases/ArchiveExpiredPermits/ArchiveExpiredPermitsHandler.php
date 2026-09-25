<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\ArchiveExpiredPermits;

use App\Contracts\Utils\ClockInterface;
use App\Modules\Permit\Domain\PermitArchiveRepositoryInterface;
use App\Modules\Permit\Domain\PermitRepositoryInterface;
use App\SharedKernel\Application\Command\CommandWithResultHandlerInterface;
use Override;

/**
 * Verschiebt abgelaufene Genehmigungen in Chunks ins Archiv und anonymisiert Altbestände.
 *
 * @implements CommandWithResultHandlerInterface<ArchiveExpiredPermitsCommand, int>
 */
final readonly class ArchiveExpiredPermitsHandler implements CommandWithResultHandlerInterface
{
    public function __construct(
        private PermitRepositoryInterface $repository,
        private PermitArchiveRepositoryInterface $archiveRepository,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param ArchiveExpiredPermitsCommand $command
     *
     * @return int Anzahl der DSGVO-konform anonymisierten Alt-Datensätze.
     */
    #[Override]
    public function handle(mixed $command): int
    {
        $cutoffDate = $this->clock->now()->modify("-{$command->graceDays} days")->setTime(0, 0, 0);
        $chunkSize = 100;

        $codesToDelete = [];
        $permitsToArchive = [];

        // Nutzt die hochperformante SQL-Suche als Generator (yield),
        // anstatt alle abgelaufenen Permits auf einmal in den RAM zu laden!
        foreach ($this->repository->yieldExpired($cutoffDate) as $permit) {
            $codesToDelete[] = $permit->code->value;
            $permitsToArchive[] = $permit;

            // Chunking: Sobald 100 erreicht sind, abarbeiten und Arrays leeren (Speicher freigeben)
            if (\count($codesToDelete) < $chunkSize) {
                continue;
            }

            $this->archiveRepository->archivePermits(0, $permitsToArchive);
            $this->repository->deleteMultiple($codesToDelete);

            $codesToDelete = [];
            $permitsToArchive = [];
        }

        // Restliche Daten abarbeiten, falls das Array nicht exakt durch 100 teilbar war
        if (\count($codesToDelete) > 0) {
            $this->archiveRepository->archivePermits(0, $permitsToArchive);
            $this->repository->deleteMultiple($codesToDelete);
        }

        return $this->archiveRepository->anonymizeOldRecords($command->anonymizeYearsThreshold);
    }
}
