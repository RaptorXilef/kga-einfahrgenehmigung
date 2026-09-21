<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Application\UseCases\CalculateVoucherDiscount;

/**
 * DTO für die Antwort der Preisberechnung.
 */
final readonly class VoucherDiscountDto
{
    public function __construct(
        public float $finalPrice,
        public bool $isValid,
        public string $discountText,
        public string $errorMessage,
    ) {
    }
}
