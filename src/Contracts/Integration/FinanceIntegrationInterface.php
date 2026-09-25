<?php

declare(strict_types=1);

namespace App\Contracts\Integration;

/**
 * Port für das Finance-Modul (Bounded Context Kommunikation).
 */
interface FinanceIntegrationInterface
{
    public function generateEpcQrData(float $amount, string $reference): string;
}
