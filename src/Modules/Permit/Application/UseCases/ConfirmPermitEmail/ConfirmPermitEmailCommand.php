<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\ConfirmPermitEmail;

use App\SharedKernel\Application\Command\CommandInterface;

final readonly class ConfirmPermitEmailCommand implements CommandInterface
{
    public function __construct(
        public string $tokenOrCode,
        public ConfirmPermitEmailContext $context = new ConfirmPermitEmailContext(),
    ) {
    }
}
