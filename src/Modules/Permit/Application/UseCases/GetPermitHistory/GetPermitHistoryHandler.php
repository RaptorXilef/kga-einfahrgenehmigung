<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetPermitHistory;

use App\Contracts\Storage\StorageInterface;
use App\Core\Security\Sanitizer;
use App\Modules\Permit\Domain\Permit;
use App\SharedKernel\Application\Query\QueryHandlerInterface;

/**
 * @implements QueryHandlerInterface<GetPermitHistoryQuery, Permit[]>
 */
final readonly class GetPermitHistoryHandler implements QueryHandlerInterface
{
    public function __construct(private StorageInterface $storage)
    {
    }

    public function handle(mixed $query): array
    {
        $all = $this->storage->getAll();
        $normalizedSearch = Sanitizer::normalizeEmail($query->email);

        return \array_filter($all, function (Permit $permit) use ($normalizedSearch): bool {
            return Sanitizer::normalizeEmail($permit->getOwnerEmail()) === $normalizedSearch;
        });
    }
}
