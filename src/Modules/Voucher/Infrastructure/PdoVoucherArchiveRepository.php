<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Infrastructure;

use App\Modules\Voucher\Domain\VoucherArchiveRepositoryInterface;
use PDO;

final readonly class PdoVoucherArchiveRepository implements VoucherArchiveRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function archiveRedemption(string $code, string $userName, string $userPlot): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO vouchers_archive (code, redeemed_at, user_name, user_plot) VALUES (?, NOW(), ?, ?)');
        $stmt->execute([
            $code,
            $userName,
            $userPlot,
        ]);
    }
}
