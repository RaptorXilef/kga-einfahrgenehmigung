<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\ArchiveExpiredPermits;

use App\Contracts\Utils\ClockInterface;
use App\Modules\Permit\Domain\PermitArchiveRepositoryInterface;
use App\Modules\Permit\Domain\PermitRepositoryInterface;
use App\SharedKernel\Application\Command\CommandHandlerInterface;

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

    public function handle(mixed $command): void
    {
        $cutoffDate = $this->clock->now()->modify("-{$command->graceDays} days")->setTime(0, 0, 0);

        // Nutzt jetzt die hochperformante SQL-Suche, anstatt alle Permits in den RAM zu laden!
        $expiredPermits = $this->repository->findExpired($cutoffDate);

        if ($expiredPermits === []) {
            return;
        }

        $codesToDelete = [];
        foreach ($expiredPermits as $permit) {
            $codesToDelete[] = $permit->code->value;
        }

        $this->archiveRepository->archivePermits(0, $expiredPermits);
        $this->repository->deleteMultiple($codesToDelete);
    }
}
