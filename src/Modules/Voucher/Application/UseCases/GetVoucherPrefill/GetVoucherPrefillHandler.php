<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Application\UseCases\GetVoucherPrefill;

use App\Contracts\Utils\ClockInterface;
use App\Modules\Voucher\Domain\Voucher;
use App\Modules\Voucher\Domain\VoucherRepositoryInterface;
use App\SharedKernel\Application\Query\QueryHandlerInterface;
use App\SharedKernel\Application\Query\QueryInterface;
use Override;

/**
 * @implements QueryHandlerInterface<GetVoucherPrefillQuery, ?VoucherPrefillDto>
 */
final readonly class GetVoucherPrefillHandler implements QueryHandlerInterface
{
    public function __construct(
        private VoucherRepositoryInterface $repository,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param GetVoucherPrefillQuery $query
     */
    #[Override]
    public function handle(QueryInterface $query): ?VoucherPrefillDto
    {
        $voucher = $this->repository->findByCode($query->code);

        if (!$voucher instanceof Voucher || $voucher->isDeactivated() || $voucher->isExpired($this->clock->now())) {
            return null;
        }

        $isDepleted = ($voucher->isMultiUse && $voucher->getCurrentUses() >= $voucher->maxUses)
            || (!$voucher->isMultiUse && $voucher->getCurrentUses() > 0);

        if ($isDepleted) {
            return null;
        }

        return new VoucherPrefillDto(
            $voucher->code,
            $voucher->reason,
            $voucher->templateKey,
            $voucher->prefillData,
        );
    }
}
