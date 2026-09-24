<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\ArchiveExpiredPermits;

use App\Contracts\Utils\ClockInterface;
use App\Modules\Permit\Domain\PermitArchiveRepositoryInterface;
use App\Modules\Permit\Domain\PermitRepositoryInterface;
use App\SharedKernel\Application\Command\CommandHandlerInterface;
use Override;

/**
 * @implements CommandHandlerInterface<ArchiveExpiredPermitsCommand>
 */
final readonly class ArchiveExpiredPermitsHandler implements CommandHandlerInterface
{
    public function __construct(
        private PermitRepositoryInterface $repository,
        private PermitArchiveRepositoryInterface $archiveRepository,
        private ClockInterface $clock,
    ) {
    }

    #[Override]
    public function handle(mixed $command): void
    {
        $cutoffDate = $this->clock->now()->modify("-{$command->graceDays} days")->setTime(0, 0, 0);
        $chunkSize = 100;

        $codesToDelete = [];
        $permitsToArchive = [];

        // Nutzt jetzt die hochperformante SQL-Suche als Generator (yield),
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
        if (\count($codesToDelete) <= 0) {
            return;
        }

        $this->archiveRepository->archivePermits(0, $permitsToArchive);
        $this->repository->deleteMultiple($codesToDelete);
    }
}
