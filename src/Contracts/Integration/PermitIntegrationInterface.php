<?php

declare(strict_types=1);

namespace App\Contracts\Integration;

/**
 * Port für das Permit-Modul (Bounded Context Kommunikation).
 */
interface PermitIntegrationInterface
{
    public function hasPermits(string $email): bool;

    /**
     * Anonymisiert abgelaufene Archiv-Einträge, die älter als der angegebene Schwellenwert (in Jahren) sind.
     */
    public function anonymizeArchive(int $yearsThreshold = 10): int;
}
