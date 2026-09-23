<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetPermitHistory;

use App\Modules\Permit\Domain\Permit;
use App\Modules\Permit\Domain\PermitRepositoryInterface;
use App\SharedKernel\Application\Query\QueryHandlerInterface;
use App\SharedKernel\Application\Security\Sanitizer;

/**
 * @implements QueryHandlerInterface<GetPermitHistoryQuery, Permit[]>
 */
final readonly class GetPermitHistoryHandler implements QueryHandlerInterface
{
    public function __construct(private PermitRepositoryInterface $repository)
    {
    }

    public function handle(mixed $query): array
    {
        // Deutlich performanter: Wir laden nur Genehmigungen, die überhaupt eine E-Mail haben.
        $all = $this->repository->findAllWithEmail();
        $normalizedSearch = Sanitizer::normalizeEmail($query->email);

        return \array_filter($all, fn (Permit $permit): bool => Sanitizer::normalizeEmail($permit->getOwnerEmail()) === $normalizedSearch);
    }
}
