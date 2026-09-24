<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Application\Services;

use App\Contracts\Integration\VoucherDiscountResult;
use App\Contracts\Integration\VoucherIntegrationInterface;
use App\Modules\Voucher\Application\UseCases\CalculateVoucherDiscount\CalculateVoucherDiscountHandler;
use App\Modules\Voucher\Application\UseCases\CalculateVoucherDiscount\CalculateVoucherDiscountQuery;
use App\Modules\Voucher\Application\UseCases\RedeemVoucher\RedeemVoucherCommand;
use App\Modules\Voucher\Application\UseCases\RedeemVoucher\RedeemVoucherHandler;

/**
 * Adapter-Implementierung für externe Bounded Contexts.
 */
final readonly class VoucherIntegrationService implements VoucherIntegrationInterface
{
    public function __construct(
        private CalculateVoucherDiscountHandler $discountHandler,
        private RedeemVoucherHandler $redeemHandler,
    ) {
    }

    public function calculateDiscount(string $code, float $originalPrice): VoucherDiscountResult
    {
        $dto = $this->discountHandler->handle(new CalculateVoucherDiscountQuery($code, $originalPrice));

        return new VoucherDiscountResult(
            finalPrice: $dto->finalPrice,
            isValid: $dto->isValid,
            discountText: $dto->discountText,
            errorMessage: $dto->errorMessage,
        );
    }

    public function redeemVoucher(string $code, string $userName, string $userPlot): void
    {
        $this->redeemHandler->handle(new RedeemVoucherCommand($code, $userName, $userPlot));
    }
}
