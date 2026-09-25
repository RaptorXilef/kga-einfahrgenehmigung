<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Application\UseCases\GetVoucherList;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Utils\ClockInterface;
use App\SharedKernel\Application\Query\QueryHandlerInterface;
use App\SharedKernel\Application\Query\QueryInterface;
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
    public function handle(QueryInterface $query): array
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

        while (\is_array($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
            $code = (string) $row['code'];
            $isMultiUse = (bool) $row['is_multi_use'];
            $currentUses = (int) $row['current_uses'];
            $maxUses = (int) $row['max_uses'];
            $status = (string) $row['status'];
            $type = (string) $row['type'];
            $value = (float) $row['value'];

            $expiresAtStr = \trim((string) ($row['expires_at'] ?? ''));
            $expiresAtObj = $expiresAtStr !== '' ? new DateTimeImmutable($expiresAtStr) : null;

            $decodedPrefill = \json_decode((string) ($row['prefill_data'] ?? ''), true);
            $prefill = \is_array($decodedPrefill) ? $decodedPrefill : [];

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

            $datumVonPrefill = \trim((string) ($prefill['datum_von'] ?? ''));
            $namePrefill = \trim((string) ($prefill['name'] ?? ''));
            $plotPrefill = \trim((string) ($prefill['parzelle'] ?? ''));

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
                dateModeText: $datumVonPrefill === '' ? 'Flexible Datenwahl' : 'Gefixte Daten',
                prefilledName: $namePrefill !== '' ? $namePrefill : null,
                prefilledPlot: $plotPrefill !== '' ? $plotPrefill : '?',
                expiresText: $expiresAtObj instanceof DateTimeImmutable ? "Gültig bis: <strong class=\"u-color-dark\">{$expiresAtObj->format('d.m.Y H:i')} Uhr</strong>" : null,
                isDeactivated: $isDeactivated,
                toggleActionUrl: $isDeactivated ? 'activate_voucher' : 'deactivate_voucher',
                toggleButtonClass: $isDeactivated ? 'c-button--success' : 'c-button--danger',
                toggleIcon: $isDeactivated ? 'unlock.webp' : 'denied.webp',
                toggleTitle: $isDeactivated ? 'Aktivieren' : 'Sperren',
            );
        }

        return $dtos;
    }
}
