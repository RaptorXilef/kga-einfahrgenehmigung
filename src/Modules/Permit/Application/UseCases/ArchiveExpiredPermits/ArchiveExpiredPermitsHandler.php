<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\ArchiveExpiredPermits;

use App\Contracts\Storage\PermitArchiveRepositoryInterface;
use App\Contracts\Storage\StorageInterface;
use App\Contracts\Utils\ClockInterface;
use App\Modules\Permit\Domain\PermitStatus;
use App\SharedKernel\Application\Command\CommandHandlerInterface;

/**
 * @implements CommandHandlerInterface<ArchiveExpiredPermitsCommand>
 */
final readonly class ArchiveExpiredPermitsHandler implements CommandHandlerInterface
{
    public function __construct(
        private StorageInterface $storage,
        private PermitArchiveRepositoryInterface $archiveRepository,
        private ClockInterface $clock,
    ) {
    }

    public function handle(mixed $command): void
    {
        // Interface gibt bei uns void zurück. Die Anzahl der verarbeiteten Mails verwalten wir bei Cronjobs ggf. anders.
        $allPermits = $this->storage->getAll();

        $toArchive = [];
        $codesToDelete = [];
        $cutoffDate = $this->clock->now()->modify("-{$command->graceDays} days")->setTime(0, 0, 0);

        foreach ($allPermits as $permit) {
            if ($permit->getValidUntil() >= $cutoffDate) {
                continue;
            }

            if (!\in_array($permit->getStatus(), [PermitStatus::Bezahlt, PermitStatus::Storniert], true)) {
                continue;
            }

            $toArchive[] = $permit;
            $codesToDelete[] = $permit->code->value;
        }

        if ($toArchive !== []) {
            $this->archiveRepository->archivePermits(0, $toArchive);
            $this->storage->deleteMultiple($codesToDelete);
        }
    }
}
