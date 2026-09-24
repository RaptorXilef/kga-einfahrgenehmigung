<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Application\UseCases\CheckAvailableVouchers;

use App\SharedKernel\Application\Query\QueryHandlerInterface;
use Override;
use PDO;

/**
 * @implements QueryHandlerInterface<CheckAvailableVouchersQuery, bool>
 */
final readonly class CheckAvailableVouchersHandler implements QueryHandlerInterface
{
    public function __construct(
        private PDO $pdo,
    ) {
    }

    /**
     * @param CheckAvailableVouchersQuery $query
     */
    #[Override]
    public function handle(mixed $query): bool
    {
        // Hochperformanter Check: Finde 1 aktiven, nicht abgelaufenen und nicht aufgebrauchten Gutschein
        $sql = "SELECT 1 FROM vouchers
                WHERE status = 'aktiv'
                AND (expires_at IS NULL OR expires_at > NOW())
                AND (
                    (is_multi_use = 1 AND current_uses < max_uses)
                    OR
                    (is_multi_use = 0 AND current_uses = 0)
                )
                LIMIT 1";

        $stmt = $this->pdo->query($sql);

        return (bool) $stmt->fetchColumn();
    }
}
