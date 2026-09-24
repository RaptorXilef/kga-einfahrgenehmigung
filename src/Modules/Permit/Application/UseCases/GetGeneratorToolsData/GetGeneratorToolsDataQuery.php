<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetGeneratorToolsData;

use App\Contracts\Security\AuthorizationInterface;
use App\SharedKernel\Application\Query\QueryInterface;

final readonly class GetGeneratorToolsDataQuery implements QueryInterface
{
    public function __construct(
        public AuthorizationInterface $auth,
    ) {
    }
}
