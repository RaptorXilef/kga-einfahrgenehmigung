<?php

declare(strict_types=1);

namespace App\Modules\Permit\Domain;

use App\SharedKernel\Domain\ValueObject\LicensePlate;

final readonly class Vehicle
{
    public function __construct(
        public string $typ,
        public LicensePlate $kennzeichen,
        public ?string $firma = null,
    ) {
    }
}
