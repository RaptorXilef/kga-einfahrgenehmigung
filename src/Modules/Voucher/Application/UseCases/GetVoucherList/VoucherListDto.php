<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Application\UseCases\GetVoucherList;

/**
 * Read-Model für die Listenansicht von Gutscheinen.
 * Flach, stark typisiert und hochgradig performant.
 */
final readonly class VoucherListDto
{
    public function __construct(
        public string $code,
        public string $reason,
        public string $type,
        public float $value,
        public bool $isMultiUse,
        public int $maxUses,
        public int $currentUses,
        public string $status,
        public ?string $expiresAtFormatted,
        public string $createdAtFormatted,
    ) {
    }
}
