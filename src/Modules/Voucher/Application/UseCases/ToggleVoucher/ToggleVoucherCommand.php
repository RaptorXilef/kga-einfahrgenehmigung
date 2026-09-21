<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Application\UseCases\ToggleVoucher;

use App\SharedKernel\Application\Command\CommandInterface;

/**
 * Command zum Ändern des Status eines Gutscheins.
 */
final readonly class ToggleVoucherCommand implements CommandInterface
{
    public function __construct(
        public string $code,
        public string $targetStatus, // 'aktiv' oder 'deaktiviert'
    ) {
    }
}
