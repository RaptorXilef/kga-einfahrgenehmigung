<?php

declare(strict_types=1);

namespace App\Contracts\Integration;

/**
 * Globales DTO für die modulsichere Rabattberechnung.
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
