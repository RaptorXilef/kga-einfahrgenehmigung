<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Application\UseCases\GetVoucherArchive;

use App\SharedKernel\Application\Query\QueryHandlerInterface;
use DateTimeImmutable;
use PDO;

/**
 * @implements QueryHandlerInterface<GetVoucherArchiveQuery, array>
 */
final readonly class GetVoucherArchiveHandler implements QueryHandlerInterface
{
    public function __construct(
        private PDO $pdo,
    ) {
    }

    /**
     * @param GetVoucherArchiveQuery $query
     */
    public function handle(mixed $query): array
    {
        // Pragmatischer Direkt-Query für das Dashboard (CQRS Read-Model)
        $stmt = $this->pdo->query('SELECT * FROM vouchers_archive ORDER BY redeemed_at DESC LIMIT 500');

        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $dt = new DateTimeImmutable($row['redeemed_at']);
            $row['redeemed_at_formatted'] = $dt->format('d.m.y');
            $rows[] = $row;
        }

        return $rows;
    }
}
