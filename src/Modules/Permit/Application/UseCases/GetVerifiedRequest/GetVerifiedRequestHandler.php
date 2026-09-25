<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetVerifiedRequest;

use App\Modules\Permit\Domain\VerificationRepositoryInterface;
use App\SharedKernel\Application\Query\QueryHandlerInterface;
use Override;

/**
 * @implements QueryHandlerInterface<GetVerifiedRequestQuery, ?array>
 */
final readonly class GetVerifiedRequestHandler implements QueryHandlerInterface
{
    public function __construct(private VerificationRepositoryInterface $repository)
    {
    }

    /**
     * @param GetVerifiedRequestQuery $query
     */
    #[Override]
    public function handle(mixed $query): ?array
    {
        if ($query->token === '') {
            return null;
        }
        $all = $this->repository->loadVerified();

        return isset($all[$query->token]) ? $all[$query->token]->data : null;
    }
}
