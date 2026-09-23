<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Application\UseCases\RedeemVoucher;

use App\Modules\Voucher\Domain\Voucher;
use App\Modules\Voucher\Domain\VoucherArchiveRepositoryInterface;
use App\Modules\Voucher\Domain\VoucherRepositoryInterface;
use App\SharedKernel\Application\Command\CommandHandlerInterface;
use DomainException;

/**
 * @implements CommandHandlerInterface<RedeemVoucherCommand>
 */
final readonly class RedeemVoucherHandler implements CommandHandlerInterface
{
    public function __construct(
        private VoucherRepositoryInterface $repository,
        private VoucherArchiveRepositoryInterface $archiveRepository,
    ) {
    }

    /**
     * @param RedeemVoucherCommand $command
     */
    public function handle(mixed $command): void
    {
        $voucher = $this->repository->findByCode($command->code);

        if (!$voucher instanceof Voucher) {
            throw new DomainException('Gutscheincode nicht gefunden.');
        }

        // 1. Core Domain: Zustand ändern und speichern
        $voucher->recordUsage();
        $this->repository->save($voucher);

        // 2. Infrastruktur: Dokumentation im Archiv (Write-Model via Interface entkoppelt)
        $this->archiveRepository->archiveRedemption(
            $voucher->code,
            $command->userName,
            $command->userPlot,
        );
    }
}
