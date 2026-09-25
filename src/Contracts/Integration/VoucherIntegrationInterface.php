<?php

declare(strict_types=1);

namespace App\Contracts\Integration;

/**
 * Port für das Voucher-Modul (Bounded Context Kommunikation).
 */
interface VoucherIntegrationInterface
{
    public function calculateDiscount(string $code, float $originalPrice): VoucherDiscountResult;

    public function redeemVoucher(string $code, string $userName, string $userPlot): void;

    public function hasAvailableVouchers(): bool;

    public function getVoucherPrefill(string $code): ?VoucherPrefillResult;
}
