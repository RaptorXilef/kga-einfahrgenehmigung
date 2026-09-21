<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Application\UseCases\CalculateVoucherDiscount;

use App\SharedKernel\Application\Query\QueryInterface;

final readonly class CalculateVoucherDiscountQuery implements QueryInterface
{
    public function __construct(
        public string $code,
        public float $originalPrice,
    ) {
    }
}
