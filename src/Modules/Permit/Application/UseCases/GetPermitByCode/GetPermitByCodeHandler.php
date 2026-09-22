<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetPermitByCode;

use App\Contracts\Storage\StorageInterface;
use App\Modules\Permit\Domain\CancelledPermitRepositoryInterface;
use App\Modules\Permit\Domain\Permit;
use App\Modules\Permit\Domain\PermitArchiveRepositoryInterface;
use App\SharedKernel\Application\Query\QueryHandlerInterface;

/**
 * Löst einen Code schichtenübergreifend auf.
 * @implements QueryHandlerInterface<GetPermitByCodeQuery, ?Permit>
 */
final readonly class GetPermitByCodeHandler implements QueryHandlerInterface
{
    public function __construct(
        private StorageInterface $storage,
        private PermitArchiveRepositoryInterface $archiveRepository,
        private CancelledPermitRepositoryInterface $cancelledRepository,
    ) {
    }

    public function handle(mixed $query): ?Permit
    {
        $permit = $this->storage->findByHash($query->code);
        if ($permit instanceof Permit) {
            return $permit;
        }

        $permit = $this->archiveRepository->findByHash($query->code);
        if ($permit instanceof Permit) {
            return $permit;
        }

        return $this->cancelledRepository->findByHash($query->code);
    }
}
