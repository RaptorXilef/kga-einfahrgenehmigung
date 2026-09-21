<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\FinalizePermit;

use App\Modules\Permit\Domain\PermitStatus;
use App\SharedKernel\Application\Command\CommandInterface;

final readonly class FinalizePermitCommand implements CommandInterface
{
    public function __construct(
        public string $token,
        public PermitStatus $status,
        public ?string $comment = null,
    ) {
    }
}
