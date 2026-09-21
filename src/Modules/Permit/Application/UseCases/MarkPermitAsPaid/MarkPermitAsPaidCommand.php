<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\MarkPermitAsPaid;

use App\SharedKernel\Application\Command\CommandInterface;

final readonly class MarkPermitAsPaidCommand implements CommandInterface
{
    public function __construct(
        public string $code,
        public ?string $reason = null,
        public ?string $bookingDate = null,
    ) {
    }
}
