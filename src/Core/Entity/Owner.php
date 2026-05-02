<?php

declare(strict_types=1);

namespace App\Core\Entity;

final readonly class Owner
{
    public function __construct(
        public string $name,     // Name des Nutzers
        public string $email,    // E-Mail-Adresse des Nutzers
        public string $parzelle, // Immer 4-stellig (0020)
    ) {
    }
}
