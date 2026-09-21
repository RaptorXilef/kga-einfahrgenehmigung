<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetPermitByCode;

use App\SharedKernel\Application\Query\QueryInterface;

final readonly class GetPermitByCodeQuery implements QueryInterface
{
    public function __construct(public string $code)
    {
    }
}
