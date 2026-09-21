<?php

declare(strict_types=1);

namespace App\Modules\Permit\Domain;

use App\SharedKernel\Domain\ValueObject\Price;
use DateTimeImmutable;

final readonly class Validity
{
    public function __construct(
        public DateTimeImmutable $von,
        public DateTimeImmutable $bis,
        public Price $preis,
        public string $zweck,
    ) {
    }
}
