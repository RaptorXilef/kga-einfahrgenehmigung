<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Application\Services;

use App\Contracts\Integration\VoucherDiscountResult;
use App\Contracts\Integration\VoucherIntegrationInterface;
use App\Contracts\Integration\VoucherPrefillResult;
use App\Modules\Voucher\Application\UseCases\CalculateVoucherDiscount\CalculateVoucherDiscountHandler;
use App\Modules\Voucher\Application\UseCases\CalculateVoucherDiscount\CalculateVoucherDiscountQuery;
use App\Modules\Voucher\Application\UseCases\CheckAvailableVouchers\CheckAvailableVouchersHandler;
use App\Modules\Voucher\Application\UseCases\CheckAvailableVouchers\CheckAvailableVouchersQuery;
use App\Modules\Voucher\Application\UseCases\GetVoucherPrefill\GetVoucherPrefillHandler;
use App\Modules\Voucher\Application\UseCases\GetVoucherPrefill\GetVoucherPrefillQuery;
use App\Modules\Voucher\Application\UseCases\GetVoucherPrefill\VoucherPrefillDto;
use App\Modules\Voucher\Application\UseCases\RedeemVoucher\RedeemVoucherCommand;
use App\Modules\Voucher\Application\UseCases\RedeemVoucher\RedeemVoucherHandler;
use Override;

/**
 * Adapter-Implementierung für externe Bounded Contexts.
 */
final readonly class VoucherIntegrationService implements VoucherIntegrationInterface
{
    public function __construct(
        private CalculateVoucherDiscountHandler $discountHandler,
        private RedeemVoucherHandler $redeemHandler,
        private CheckAvailableVouchersHandler $checkAvailableHandler,
        private GetVoucherPrefillHandler $prefillHandler,
    ) {
    }

    #[Override]
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

    #[Override]
    public function redeemVoucher(string $code, string $userName, string $userPlot): void
    {
        $this->redeemHandler->handle(new RedeemVoucherCommand($code, $userName, $userPlot));
    }

    #[Override]
    public function hasAvailableVouchers(): bool
    {
        return $this->checkAvailableHandler->handle(new CheckAvailableVouchersQuery());
    }

    #[Override]
    public function getVoucherPrefill(string $code): ?VoucherPrefillResult
    {
        $dto = $this->prefillHandler->handle(new GetVoucherPrefillQuery($code));

        if (!$dto instanceof VoucherPrefillDto) {
            return null;
        }

        return new VoucherPrefillResult(
            code: $dto->code,
            reason: $dto->reason,
            templateKey: $dto->templateKey,
            data: $dto->data,
        );
    }
}
