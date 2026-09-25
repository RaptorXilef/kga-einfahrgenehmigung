<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetVerifiedRequest;

use App\Modules\Permit\Domain\VerificationRepositoryInterface;
use App\Modules\Permit\Domain\VerificationRequest;
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

        $req = $this->repository->findVerifiedByToken($query->token);

        return $req instanceof VerificationRequest ? $req->data : null;
    }
}
