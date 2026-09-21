<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Application\UseCases\CreateVoucher;

use App\SharedKernel\Application\Command\CommandInterface;

/**
 * Command: Transportiert die exakten Anweisungen zur Erstellung eines Gutscheins.
 * Rein lesbar, keine Logik.
 */
final readonly class CreateVoucherCommand implements CommandInterface
{
    public function __construct(
        public string $reason,
        public string $createdBy,
        public string $templateKey,
        public array $prefillData,
        public string $type,
        public float $value,
        public bool $isMultiUse,
        public int $maxUses,
        public string $customCode,
        public ?string $expiresAt,
        public string $dateMode,
    ) {
    }
}
