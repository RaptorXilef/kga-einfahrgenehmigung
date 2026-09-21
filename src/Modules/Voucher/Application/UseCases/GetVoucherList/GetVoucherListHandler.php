<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Application\UseCases\GetVoucherList;

use App\SharedKernel\Application\Query\QueryHandlerInterface;
use DateTimeImmutable;
use PDO;

/**
 * Holt die Gutschein-Daten direkt und ungefiltert aus der Datenbank.
 *
 * @implements QueryHandlerInterface<GetVoucherListQuery, array<VoucherListDto>>
 */
final readonly class GetVoucherListHandler implements QueryHandlerInterface
{
    public function __construct(
        private PDO $pdo,
    ) {
    }

    /**
     * @param GetVoucherListQuery $query
     * @return array<VoucherListDto>
     */
    public function handle(mixed $query): array
    {
        $sql = 'SELECT * FROM vouchers';
        $params = [];

        if ($query->statusFilter !== null) {
            $sql .= ' WHERE status = :status';
            $params['status'] = $query->statusFilter;
        }

        $sql .= ' ORDER BY created_at DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $dtos = [];
        foreach ($rows as $row) {
            $expiresAt = null;
            if (!empty($row['expires_at'])) {
                $expiresAt = (new DateTimeImmutable($row['expires_at']))->format('d.m.Y H:i');
            }

            $dtos[] = new VoucherListDto(
                code: (string) $row['code'],
                reason: (string) $row['reason'],
                type: (string) $row['type'],
                value: (float) $row['value'],
                isMultiUse: (bool) $row['is_multi_use'],
                maxUses: (int) $row['max_uses'],
                currentUses: (int) $row['current_uses'],
                status: (string) $row['status'],
                expiresAtFormatted: $expiresAt,
                createdAtFormatted: (new DateTimeImmutable($row['created_at']))->format('d.m.Y H:i'),
            );
        }

        return $dtos;
    }
}
