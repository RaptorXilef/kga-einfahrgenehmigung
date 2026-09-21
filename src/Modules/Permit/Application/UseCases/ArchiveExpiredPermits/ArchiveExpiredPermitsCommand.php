<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\ArchiveExpiredPermits;

use App\SharedKernel\Application\Command\CommandInterface;

final readonly class ArchiveExpiredPermitsCommand implements CommandInterface
{
    public function __construct(public int $graceDays)
    {
    }
}
