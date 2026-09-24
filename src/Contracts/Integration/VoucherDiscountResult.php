<?php

declare(strict_types=1);

namespace App\Contracts\Integration;

/**
 * Globales DTO für die modulsichere Rabattberechnung.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class VoucherDiscountResult
{
    public function __construct(
        public float $finalPrice,
        public bool $isValid,
        public string $discountText,
        public string $errorMessage,
    ) {
    }
}
