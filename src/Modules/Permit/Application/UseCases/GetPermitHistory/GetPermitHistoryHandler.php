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
        $normalizedSearch = Sanitizer::normalizeEmail($query->email);
        $result = [];

        // Deutlich performanter: Wir iterieren als Stream (Generator) durch die Datensätze,
        // prüfen das Pächter-Login-Kriterium und bauen nur dann das Ergebnis-Array auf.
        foreach ($this->repository->yieldAllWithEmail() as $permit) {
            if (Sanitizer::normalizeEmail($permit->getOwnerEmail()) === $normalizedSearch) {
                $result[] = $permit;
            }
        }

        return $result;
    }
}
