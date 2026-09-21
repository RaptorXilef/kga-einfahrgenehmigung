<?php

declare(strict_types=1);

namespace App\Modules\Permit\Domain;

use App\SharedKernel\Domain\ValueObject\EmailAddress;
use App\SharedKernel\Domain\ValueObject\PlotNumber;

final readonly class Owner
{
    public function __construct(
        public string $name,
        public ?EmailAddress $email,
        public PlotNumber $parzelle,
    ) {
    }
}
