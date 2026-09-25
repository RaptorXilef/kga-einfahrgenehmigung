<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Application\UseCases\CalculateVoucherDiscount;

use App\Contracts\Utils\ClockInterface;
use App\SharedKernel\Application\Query\QueryHandlerInterface;
use App\SharedKernel\Application\Query\QueryInterface;
use DateTimeImmutable;
use Override;
use PDO;

/**
 * Berechnet den Gutschein-Rabatt direkt über PDO ohne Entity-Hydrierung (Pragmatic CQRS).
 *
 * @implements QueryHandlerInterface<CalculateVoucherDiscountQuery, VoucherDiscountDto>
 */
final readonly class CalculateVoucherDiscountHandler implements QueryHandlerInterface
{
    public function __construct(
        private PDO $pdo,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param CalculateVoucherDiscountQuery $query
     */
    #[Override]
    public function handle(QueryInterface $query): VoucherDiscountDto
    {
        $stmt = $this->pdo->prepare(
            'SELECT type, value, is_multi_use, max_uses, current_uses, expires_at, status FROM vouchers WHERE code = :code LIMIT 1',
        );
        $stmt->execute(['code' => \strtoupper(\trim($query->code))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // 1. Existenz-Prüfung
        if (!\is_array($row)) {
            return new VoucherDiscountDto($query->originalPrice, false, '', 'Ungültiger Code');
        }

        // 2. Status-Prüfung
        if ((string) ($row['status'] ?? '') === 'deaktiviert') {
            return new VoucherDiscountDto($query->originalPrice, false, '', 'Code gesperrt');
        }

        $expiresAtStr = \trim((string) ($row['expires_at'] ?? ''));
        if ($expiresAtStr !== '' && new DateTimeImmutable($expiresAtStr) < $this->clock->now()) {
            return new VoucherDiscountDto($query->originalPrice, false, '', 'Code abgelaufen');
        }

        // 3. Nutzungs-Prüfung
        $isMultiUse = (bool) ($row['is_multi_use'] ?? false);
        $currentUses = (int) ($row['current_uses'] ?? 0);
        $maxUses = (int) ($row['max_uses'] ?? 1);

        $isDepleted = ($isMultiUse && $currentUses >= $maxUses)
            || (!$isMultiUse && $currentUses > 0);

        if ($isDepleted) {
            return new VoucherDiscountDto($query->originalPrice, false, '', 'Code aufgebraucht');
        }

        // 4. Rabatt berechnen
        $type = (string) ($row['type'] ?? 'free');
        $value = (float) ($row['value'] ?? 0.0);
        $finalPrice = $query->originalPrice;
        $discountText = '';

        if ($type === 'free') {
            $finalPrice = 0.0;
            $discountText = '100% Rabatt (Kostenlos)';
        } elseif ($type === 'percent') {
            $discount = $query->originalPrice * $value / 100;
            $finalPrice = \max(0.0, $query->originalPrice - $discount);
            $discountText = $value . '% Rabatt';
        } elseif ($type === 'fixed') {
            $finalPrice = \max(0.0, $query->originalPrice - $value);
            $discountText = 'Sonderpreis aktiviert';
        }

        return new VoucherDiscountDto($finalPrice, true, $discountText, '');
    }
}
