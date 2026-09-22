<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetVerifiedRequest;

use App\Modules\Permit\Domain\VerificationRepositoryInterface;

final readonly class GetVerifiedRequestHandler
{
    public function __construct(private VerificationRepositoryInterface $repository)
    {
    }

    public function handle(GetVerifiedRequestQuery $query): ?array
    {
        if ($query->token === '') {
            return null;
        }
        $all = $this->repository->loadVerified();

        return isset($all[$query->token]) ? $all[$query->token]->data : null;
    }
}
