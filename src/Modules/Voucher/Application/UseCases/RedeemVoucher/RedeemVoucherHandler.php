<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Application\UseCases\RedeemVoucher;

use App\Modules\Voucher\Domain\VoucherRepositoryInterface;
use App\SharedKernel\Application\Command\CommandHandlerInterface;
use DomainException;
use PDO;

/**
 * @implements CommandHandlerInterface<RedeemVoucherCommand>
 */
final readonly class RedeemVoucherHandler implements CommandHandlerInterface
{
    public function __construct(
        private VoucherRepositoryInterface $repository,
        private PDO $pdo,
    ) {
    }

    /**
     * @param RedeemVoucherCommand $command
     */
    public function handle(mixed $command): void
    {
        $voucher = $this->repository->findByCode($command->code);

        if ($voucher === null) {
            throw new DomainException('Gutscheincode nicht gefunden.');
        }

        // 1. Core Domain: Zustand ändern und speichern
        $voucher->recordUsage();
        $this->repository->save($voucher);

        // 2. Infrastruktur: Dokumentation im Archiv (Write-Model)
        $stmt = $this->pdo->prepare('INSERT INTO vouchers_archive (code, redeemed_at, user_name, user_plot) VALUES (?, NOW(), ?, ?)');
        $stmt->execute([
            $voucher->code,
            $command->userName,
            $command->userPlot,
        ]);
    }
}
