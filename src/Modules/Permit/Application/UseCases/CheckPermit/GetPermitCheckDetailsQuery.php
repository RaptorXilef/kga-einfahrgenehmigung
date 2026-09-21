<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\CheckPermit;

use App\SharedKernel\Application\Query\QueryInterface;

final readonly class GetPermitCheckDetailsQuery implements QueryInterface
{
    public function __construct(
        public string $codeOrPlate,
        public bool $isAdminAuth,
        public string $token,
    ) {
    }
}
