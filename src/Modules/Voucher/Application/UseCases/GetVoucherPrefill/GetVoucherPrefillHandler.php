<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Application\UseCases\GetVoucherPrefill;

use App\Contracts\Utils\ClockInterface;
use App\SharedKernel\Application\Query\QueryHandlerInterface;
use App\SharedKernel\Application\Query\QueryInterface;
use DateTimeImmutable;
use Override;
use PDO;

/**
 * Löst die Gutschein-Vorbefüllung direkt über PDO ohne Entity-Hydrierung auf (Pragmatic CQRS).
 *
 * @implements QueryHandlerInterface<GetVoucherPrefillQuery, ?VoucherPrefillDto>
 */
final readonly class GetVoucherPrefillHandler implements QueryHandlerInterface
{
    public function __construct(
        private PDO $pdo,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param GetVoucherPrefillQuery $query
     */
    #[Override]
    public function handle(QueryInterface $query): ?VoucherPrefillDto
    {
        $stmt = $this->pdo->prepare(
            'SELECT code, reason, template_key, is_multi_use, max_uses, current_uses, expires_at, status, prefill_data FROM vouchers WHERE code = :code LIMIT 1',
        );
        $stmt->execute(['code' => \strtoupper(\trim($query->code))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!\is_array($row) || (string) ($row['status'] ?? '') === 'deaktiviert') {
            return null;
        }

        $expiresAtStr = \trim((string) ($row['expires_at'] ?? ''));
        if ($expiresAtStr !== '' && new DateTimeImmutable($expiresAtStr) < $this->clock->now()) {
            return null;
        }

        $isMultiUse = (bool) ($row['is_multi_use'] ?? false);
        $currentUses = (int) ($row['current_uses'] ?? 0);
        $maxUses = (int) ($row['max_uses'] ?? 1);

        $isDepleted = ($isMultiUse && $currentUses >= $maxUses)
            || (!$isMultiUse && $currentUses > 0);

        if ($isDepleted) {
            return null;
        }

        $decodedPrefill = \json_decode((string) ($row['prefill_data'] ?? ''), true);

        return new VoucherPrefillDto(
            (string) $row['code'],
            (string) ($row['reason'] ?? ''),
            (string) ($row['template_key'] ?? 'std_7'),
            \is_array($decodedPrefill) ? $decodedPrefill : [],
        );
    }
}
