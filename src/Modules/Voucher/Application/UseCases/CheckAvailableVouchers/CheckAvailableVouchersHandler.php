<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Application\UseCases\CheckAvailableVouchers;

use App\Contracts\Utils\ClockInterface;
use App\SharedKernel\Application\Query\QueryHandlerInterface;
use App\SharedKernel\Application\Query\QueryInterface;
use Override;
use PDO;

/**
 * @implements QueryHandlerInterface<CheckAvailableVouchersQuery, bool>
 */
final readonly class CheckAvailableVouchersHandler implements QueryHandlerInterface
{
    public function __construct(
        private PDO $pdo,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param CheckAvailableVouchersQuery $query
     */
    #[Override]
    public function handle(QueryInterface $query): bool
    {
        // Hochperformanter Check: Finde 1 aktiven, nicht abgelaufenen und nicht aufgebrauchten Gutschein
        $sql = "SELECT 1 FROM vouchers
                WHERE status = 'aktiv'
                AND (expires_at IS NULL OR expires_at > :now)
                AND (
                    (is_multi_use = 1 AND current_uses < max_uses)
                    OR
                    (is_multi_use = 0 AND current_uses = 0)
                )
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['now' => $this->clock->now()->format('Y-m-d H:i:s')]);

        return (bool) $stmt->fetchColumn();
    }
}
