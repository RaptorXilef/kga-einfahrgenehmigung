<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Application\UseCases\GetVoucherPrefill;

final readonly class VoucherPrefillDto
{
    public function __construct(
        public string $code,
        public string $reason,
        public string $templateKey,
        public array $data,
    ) {
    }
}
