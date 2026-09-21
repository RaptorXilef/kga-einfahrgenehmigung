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

        $now = new DateTimeImmutable();
        $dtos = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $isMultiUse = (bool) $row['is_multi_use'];
            $currentUses = (int) $row['current_uses'];
            $maxUses = (int) $row['max_uses'];
            $status = (string) $row['status'];
            $type = (string) $row['type'];
            $value = (float) $row['value'];

            $expiresAtObj = $row['expires_at'] ? new DateTimeImmutable($row['expires_at']) : null;
            $prefill = \json_decode((string) $row['prefill_data'], true) ?: [];

            // 1. Logik-Auswertung
            $isDeactivated = $status !== 'aktiv';
            $isExpired = $expiresAtObj !== null && $expiresAtObj < $now;
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
                code: (string) $row['code'],
                reason: $reasonText,
                isInvalid: $isInvalid,
                rowClass: $isInvalid ? 'c-table__row--danger u-opacity-50' : '',
                discountText: $discountText,
                discountBadgeClass: $type === 'free' ? 'c-badge--success' : 'c-badge--primary',
                usageBadgeText: $isMultiUse ? "Mehrfach ({$currentUses}/" . ($maxUses > 0 ? $maxUses : '&infin;') . ')' : 'Einweg',
                usageBadgeIcon: $isMultiUse ? 'sync.webp' : null,
                dateModeText: empty($prefill['datum_von']) ? 'Flexible Datenwahl' : 'Gefixte Daten',
                prefilledName: !empty($prefill['name']) ? (string) $prefill['name'] : null,
                prefilledPlot: !empty($prefill['parzelle']) ? (string) $prefill['parzelle'] : null,
                expiresText: $expiresAtObj ? "Gültig bis: <strong class=\"u-color-dark\">{$expiresAtObj->format('d.m.Y H:i')} Uhr</strong>" : null,
                isDeactivated: $isDeactivated,
                toggleActionUrl: $isDeactivated ? 'activate_voucher' : 'deactivate_voucher',
                toggleIcon: $isDeactivated ? 'unlock.webp' : 'denied.webp',
                toggleTitle: $isDeactivated ? 'Aktivieren' : 'Sperren',
            );
        }

        return $dtos;
    }
}
