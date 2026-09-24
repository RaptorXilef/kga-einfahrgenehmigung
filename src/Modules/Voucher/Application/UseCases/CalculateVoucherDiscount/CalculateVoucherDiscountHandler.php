<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Application\UseCases\CalculateVoucherDiscount;

use App\Modules\Voucher\Domain\Voucher;
use App\Modules\Voucher\Domain\VoucherRepositoryInterface;
use App\SharedKernel\Application\Query\QueryHandlerInterface;
use DateTimeImmutable;
use Override;

/**
 * @implements QueryHandlerInterface<CalculateVoucherDiscountQuery, VoucherDiscountDto>
 */
final readonly class CalculateVoucherDiscountHandler implements QueryHandlerInterface
{
    public function __construct(
        private VoucherRepositoryInterface $repository,
    ) {
    }

    /**
     * @param CalculateVoucherDiscountQuery $query
     */
    #[Override]
    public function handle(mixed $query): VoucherDiscountDto
    {
        $voucher = $this->repository->findByCode($query->code);

        // 1. Existenz-Prüfung
        if (!$voucher instanceof Voucher) {
            return new VoucherDiscountDto($query->originalPrice, false, '', 'Ungültiger Code');
        }

        // 2. Status-Prüfung
        if ($voucher->isDeactivated()) {
            return new VoucherDiscountDto($query->originalPrice, false, '', 'Code gesperrt');
        }

        if ($voucher->isExpired(new DateTimeImmutable())) {
            return new VoucherDiscountDto($query->originalPrice, false, '', 'Code abgelaufen');
        }

        // 3. Nutzungs-Prüfung
        $isDepleted = ($voucher->isMultiUse && $voucher->getCurrentUses() >= $voucher->maxUses)
            || (!$voucher->isMultiUse && $voucher->getCurrentUses() > 0);

        if ($isDepleted) {
            return new VoucherDiscountDto($query->originalPrice, false, '', 'Code aufgebraucht');
        }

        // 4. Rabatt berechnen
        $finalPrice = $query->originalPrice;
        $discountText = '';

        if ($voucher->type === 'free') {
            $finalPrice = 0.0;
            $discountText = '100% Rabatt (Kostenlos)';
        } elseif ($voucher->type === 'percent') {
            $discount = $query->originalPrice * $voucher->value / 100;
            $finalPrice = \max(0.0, $query->originalPrice - $discount);
            $discountText = $voucher->value . '% Rabatt';
        } elseif ($voucher->type === 'fixed') {
            $finalPrice = \max(0.0, $query->originalPrice - $voucher->value);
            $discountText = 'Sonderpreis aktiviert';
        }

        return new VoucherDiscountDto($finalPrice, true, $discountText, '');
    }
}
