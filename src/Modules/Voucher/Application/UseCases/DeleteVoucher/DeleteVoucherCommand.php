<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Application\UseCases\DeleteVoucher;

use App\SharedKernel\Application\Command\CommandInterface;

/**
 * Command zum endgültigen Löschen eines Gutscheins.
 */
final readonly class DeleteVoucherCommand implements CommandInterface
{
    public function __construct(
        public string $code,
    ) {
    }
}
