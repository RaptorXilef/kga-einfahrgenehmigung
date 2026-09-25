<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetVerifiedRequest;

use App\SharedKernel\Application\Query\QueryInterface;

final readonly class GetVerifiedRequestQuery implements QueryInterface
{
    public function __construct(public string $token)
    {
    }
}
