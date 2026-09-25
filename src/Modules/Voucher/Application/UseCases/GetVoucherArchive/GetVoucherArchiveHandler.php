<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Application\UseCases\GetVoucherArchive;

use App\SharedKernel\Application\Query\QueryHandlerInterface;
use DateTimeImmutable;
use Override;
use PDO;

/**
 * @implements QueryHandlerInterface<GetVoucherArchiveQuery, array<VoucherArchiveItemDto>>
 */
final readonly class GetVoucherArchiveHandler implements QueryHandlerInterface
{
    public function __construct(
        private PDO $pdo,
    ) {
    }

    /**
     * @param GetVoucherArchiveQuery $query
     *
     * @return array<VoucherArchiveItemDto>
     */
    #[Override]
    public function handle(mixed $query): array
    {
        $stmt = $this->pdo->query('SELECT code, redeemed_at, user_name, user_plot FROM vouchers_archive ORDER BY redeemed_at DESC LIMIT 500');

        $dtos = [];
        if ($stmt !== false) {
            while (\is_array($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
                $dt = new DateTimeImmutable((string) $row['redeemed_at']);
                $dtos[] = new VoucherArchiveItemDto(
                    code: (string) $row['code'],
                    redeemedAtFormatted: $dt->format('d.m.y'),
                    userName: (string) ($row['user_name'] ?? 'Unbekannt'),
                    userPlot: (string) ($row['user_plot'] ?? '0000'),
                );
            }
        }

        return $dtos;
    }
}
