<?php

declare(strict_types=1);

namespace App\Contracts\Integration;

/**
 * Port für das Permit-Modul (Bounded Context Kommunikation).
 */
interface PermitIntegrationInterface
{
    public function hasPermits(string $email): bool;
}
