<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\GetUserManagementData;

use App\Contracts\Security\AuthorizationInterface;
use App\SharedKernel\Application\Query\QueryInterface;

final readonly class GetUserManagementDataQuery implements QueryInterface
{
    public function __construct(
        public AuthorizationInterface $auth,
    ) {
    }
}
