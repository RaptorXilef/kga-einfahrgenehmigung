<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetPermitHistory;

use App\SharedKernel\Application\Query\QueryInterface;

final readonly class GetPermitHistoryQuery implements QueryInterface
{
    public function __construct(public string $email)
    {
    }
}
