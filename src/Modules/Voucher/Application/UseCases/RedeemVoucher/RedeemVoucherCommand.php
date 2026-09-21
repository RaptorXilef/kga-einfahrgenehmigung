<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Application\UseCases\RedeemVoucher;

use App\SharedKernel\Application\Command\CommandInterface;

final readonly class RedeemVoucherCommand implements CommandInterface
{
    public function __construct(
        public string $code,
        public string $userName,
        public string $userPlot,
    ) {
    }
}
