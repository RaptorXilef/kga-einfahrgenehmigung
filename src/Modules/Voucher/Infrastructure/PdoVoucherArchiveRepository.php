<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Infrastructure;

use App\Contracts\Utils\ClockInterface;
use App\Modules\Voucher\Domain\VoucherArchiveRepositoryInterface;
use App\SharedKernel\Infrastructure\Utils\SystemClock;
use Override;
use PDO;

final readonly class PdoVoucherArchiveRepository implements VoucherArchiveRepositoryInterface
{
    public function __construct(
        private PDO $pdo,
        private ClockInterface $clock = new SystemClock(),
    ) {
    }

    #[Override]
    public function archiveRedemption(string $code, string $userName, string $userPlot): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO vouchers_archive (code, redeemed_at, user_name, user_plot) VALUES (?, ?, ?, ?)');
        $stmt->execute([
            $code,
            $this->clock->now()->format('Y-m-d H:i:s'),
            $userName,
            $userPlot,
        ]);
    }
}
