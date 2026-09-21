<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\CancelPermit;

use App\SharedKernel\Application\Command\CommandInterface;

final readonly class CancelPermitCommand implements CommandInterface
{
    public function __construct(
        public string $code,
        public string $sessionEmail,
    ) {
    }
}
