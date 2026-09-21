<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Application\UseCases\GetVoucherList;

use App\SharedKernel\Application\Query\QueryInterface;

/**
 * Fordert eine Liste von Gutscheinen an.
 */
final readonly class GetVoucherListQuery implements QueryInterface
{
    public function __construct(
        public ?string $statusFilter = null, // z.B. 'aktiv' oder 'deaktiviert'
    ) {
    }
}
