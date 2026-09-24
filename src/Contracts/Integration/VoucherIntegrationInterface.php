<?php

declare(strict_types=1);

namespace App\Contracts\Integration;

/**
 * Port für das Voucher-Modul (Bounded Context Kommunikation).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
interface VoucherIntegrationInterface
{
    public function calculateDiscount(string $code, float $originalPrice): VoucherDiscountResult;

    public function redeemVoucher(string $code, string $userName, string $userPlot): void;
}
