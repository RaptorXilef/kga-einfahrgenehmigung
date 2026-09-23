<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Application\UseCases\GetVoucherPrefill;

use App\SharedKernel\Application\Query\QueryInterface;

final readonly class GetVoucherPrefillQuery implements QueryInterface
{
    public function __construct(
        public string $code,
    ) {
    }
}
