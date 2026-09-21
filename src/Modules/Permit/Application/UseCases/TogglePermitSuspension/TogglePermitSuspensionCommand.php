<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\TogglePermitSuspension;

use App\SharedKernel\Application\Command\CommandInterface;

final readonly class TogglePermitSuspensionCommand implements CommandInterface
{
    public function __construct(
        public string $code,
        public bool $isSuspended,
        public string $reason,
    ) {
    }
}
