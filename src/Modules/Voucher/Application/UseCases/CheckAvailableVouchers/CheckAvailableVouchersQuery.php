<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Application\UseCases\CheckAvailableVouchers;

use App\SharedKernel\Application\Query\QueryInterface;

/**
 * Query: Prüft, ob es im System mindestens einen einlösbaren Gutschein gibt.
 */
final readonly class CheckAvailableVouchersQuery implements QueryInterface
{
}
