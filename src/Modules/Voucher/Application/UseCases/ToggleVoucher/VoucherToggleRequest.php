<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Application\UseCases\ToggleVoucher;

use App\Application\Exception\ValidationException;

/**
 * DTO für das Aktivieren/Deaktivieren eines Gutscheins.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class VoucherToggleRequest
{
    private function __construct(
        public string $code,
        public string $targetStatus,
    ) {
    }

    public static function fromArray(array $post): self
    {
        $code = \trim((string) ($post['code'] ?? ''));

        if ($code === '') {
            throw ValidationException::withMessage('Fehler: Fehlender Parameter (code).');
        }

        $targetStatus = ($post['action'] ?? '') === 'activate_voucher' ? 'aktiv' : 'deaktiviert';

        return new self($code, $targetStatus);
    }
}
