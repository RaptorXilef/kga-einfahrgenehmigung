<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Application\UseCases\ToggleVoucher;

use App\Modules\Voucher\Domain\Voucher;
use App\Modules\Voucher\Domain\VoucherRepositoryInterface;
use App\SharedKernel\Application\Command\CommandHandlerInterface;
use App\SharedKernel\Application\Command\CommandInterface;
use DomainException;
use Override;

/**
 * @implements CommandHandlerInterface<ToggleVoucherCommand>
 */
final readonly class ToggleVoucherHandler implements CommandHandlerInterface
{
    public function __construct(
        private VoucherRepositoryInterface $repository,
    ) {
    }

    /**
     * @param ToggleVoucherCommand $command
     */
    #[Override]
    public function handle(CommandInterface $command): void
    {
        $voucher = $this->repository->findByCode($command->code);

        if (!$voucher instanceof Voucher) {
            throw new DomainException("Gutscheincode '{$command->code}' nicht gefunden.");
        }

        if ($command->targetStatus === 'aktiv') {
            $voucher->activate();
        } else {
            $voucher->deactivate();
        }

        $this->repository->save($voucher);
    }
}
