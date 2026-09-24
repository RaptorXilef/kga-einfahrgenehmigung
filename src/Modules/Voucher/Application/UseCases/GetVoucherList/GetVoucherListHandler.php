<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Application\UseCases\GetVoucherList;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Utils\ClockInterface;
use App\SharedKernel\Application\Query\QueryHandlerInterface;
use DateTimeImmutable;
use Override;
use PDO;

/**
 * Holt die Gutschein-Daten direkt und speicherschonend aus der Datenbank.
 *
 * @implements QueryHandlerInterface<GetVoucherListQuery, array<VoucherListDto>>
 */
final readonly class GetVoucherListHandler implements QueryHandlerInterface
{
    public function __construct(
        private PDO $pdo,
        private ConfigInterface $config,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param GetVoucherListQuery $query
     *
     * @return array<VoucherListDto>
     */
    #[Override]
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

        $now = $this->clock->now();
        $baseUrl = \rtrim($this->config->getBaseUrl(), '/');
        $dtos = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $code = (string) $row['code'];
            $isMultiUse = (bool) $row['is_multi_use'];
            $currentUses = (int) $row['current_uses'];
            $maxUses = (int) $row['max_uses'];
            $status = (string) $row['status'];
            $type = (string) $row['type'];
            $value = (float) $row['value'];

            $expiresAtObj = $row['expires_at'] ? new DateTimeImmutable((string) $row['expires_at']) : null;
            $prefill = \json_decode((string) $row['prefill_data'], true) ?: [];

            // 1. Logik-Auswertung
            $isDeactivated = $status !== 'aktiv';
            $isExpired = $expiresAtObj instanceof DateTimeImmutable && $expiresAtObj < $now;
            $isDepleted = ($isMultiUse && $currentUses >= $maxUses) || (!$isMultiUse && $currentUses > 0);
            $isInvalid = $isDeactivated || $isExpired || $isDepleted;

            // 2. Formatierungen für die View
            $reasonText = $row['reason'] . ($isExpired ? ' <span class="u-color-danger u-text-xs u-margin-inline-start-s">(Abgelaufen)</span>' : '');

            $discountText = 'Kostenlos';
            if ($type === 'percent') {
                $discountText = "{$value}% Rabatt";
            } elseif ($type === 'fixed') {
                $discountText = \number_format($value, 2, ',', '.') . ' € Festpreis';
            }

            $dtos[] = new VoucherListDto(
                code: $code,
                redeemUrl: $baseUrl . '/index?voucher=' . $code,
                reason: $reasonText,
                isInvalid: $isInvalid,
                rowClass: $isInvalid ? 'c-table__row--danger u-opacity-50' : '',
                discountText: $discountText,
                discountBadgeClass: $type === 'free' ? 'c-badge--success' : 'c-badge--primary',
                usageBadgeText: $isMultiUse ? "Mehrfach ({$currentUses}/" . ($maxUses > 0 ? (string) $maxUses : '&infin;') . ')' : 'Einweg',
                usageBadgeIcon: $isMultiUse ? 'sync.webp' : null,
                dateModeText: empty($prefill['datum_von']) ? 'Flexible Datenwahl' : 'Gefixte Daten',
                prefilledName: !empty($prefill['name']) ? (string) $prefill['name'] : null,
                prefilledPlot: !empty($prefill['parzelle']) ? (string) $prefill['parzelle'] : null,
                expiresText: $expiresAtObj instanceof DateTimeImmutable ? "Gültig bis: <strong class=\"u-color-dark\">{$expiresAtObj->format('d.m.Y H:i')} Uhr</strong>" : null,
                isDeactivated: $isDeactivated,
                toggleActionUrl: $isDeactivated ? 'activate_voucher' : 'deactivate_voucher',
                toggleIcon: $isDeactivated ? 'unlock.webp' : 'denied.webp',
                toggleTitle: $isDeactivated ? 'Aktivieren' : 'Sperren',
            );
        }

        return $dtos;
    }
}
