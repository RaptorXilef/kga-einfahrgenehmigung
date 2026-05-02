<?php

declare(strict_types=1);

namespace App\Core\Entity;

final readonly class Vehicle
{
    public function __construct(
        public string $typ,           // pkw, lkw
        public string $kennzeichen,   // im Format B-XX 1234
        public ?string $firma = null, // Optional für LKW
    ) {
    }
}
