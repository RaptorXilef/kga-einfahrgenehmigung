<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Infrastructure;

use App\Modules\Voucher\Domain\Voucher;
use App\Modules\Voucher\Domain\VoucherRepositoryInterface;
use DateTimeImmutable;
use PDO;

/**
 * Native PDO Implementierung des Voucher Repositories.
 * Keine ORM-Magie, reine Performance.
 */
final readonly class PdoVoucherRepository implements VoucherRepositoryInterface
{
    public function __construct(
        private PDO $pdo,
    ) {
    }

    public function save(Voucher $voucher): void
    {
        $sql = 'INSERT INTO vouchers (
                    code, template_key, reason, type, value, is_multi_use, max_uses,
                    current_uses, expires_at, status, prefill_data, created_by, created_at
                ) VALUES (
                    :code, :tpl, :reason, :type, :value, :multi, :max,
                    :curr, :exp, :status, :prefill, :cb, :ca
                )
                ON DUPLICATE KEY UPDATE
                    template_key = VALUES(template_key), reason = VALUES(reason), type = VALUES(type),
                    value = VALUES(value), is_multi_use = VALUES(is_multi_use), max_uses = VALUES(max_uses),
                    current_uses = VALUES(current_uses), expires_at = VALUES(expires_at), status = VALUES(status),
                    prefill_data = VALUES(prefill_data)';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'code' => $voucher->code,
            'tpl' => $voucher->templateKey,
            'reason' => $voucher->reason,
            'type' => $voucher->type,
            'value' => $voucher->value,
            'multi' => (int) $voucher->isMultiUse,
            'max' => $voucher->maxUses,
            'curr' => $voucher->getCurrentUses(),
            'exp' => $voucher->expiresAt?->format('Y-m-d H:i:s'),
            'status' => $voucher->getStatus(),
            'prefill' => \json_encode($voucher->prefillData, \JSON_THROW_ON_ERROR),
            'cb' => $voucher->createdBy,
            'ca' => $voucher->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findByCode(string $code): ?Voucher
    {
        $stmt = $this->pdo->prepare('SELECT * FROM vouchers WHERE code = :code');
        $stmt->execute(['code' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return new Voucher(
            code: (string) $row['code'],
            templateKey: (string) $row['template_key'],
            reason: (string) $row['reason'],
            type: (string) $row['type'],
            value: (float) $row['value'],
            isMultiUse: (bool) $row['is_multi_use'],
            maxUses: (int) $row['max_uses'],
            currentUses: (int) $row['current_uses'],
            expiresAt: $row['expires_at'] ? new DateTimeImmutable($row['expires_at']) : null,
            status: (string) $row['status'],
            prefillData: \json_decode((string) $row['prefill_data'], true) ?: [],
            createdBy: (string) $row['created_by'],
            createdAt: new DateTimeImmutable($row['created_at']),
        );
    }

    public function delete(string $code): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM vouchers WHERE code = :code');
        $stmt->execute(['code' => $code]);
    }
}
