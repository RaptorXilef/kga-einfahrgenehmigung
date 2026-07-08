<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Application\Exception\ValidationException;
use App\Core\ValueObject\LicensePlate;
use App\Core\ValueObject\PlotNumber;

/**
 * DTO für das Erstellen von Gutscheinen.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class VoucherCreateRequest
{
    private function __construct(
        public string $templateKey,
        public string $reason,
        public string $type,
        public float $value,
        public bool $isMultiUse,
        public int $maxUses,
        public string $customCode,
        public ?string $expiresAt,
        public string $dateMode,
        public array $prefillData,
    ) {
    }

    // TODO DOCBLOCK
    public static function fromArray(array $post): self
    {
        // Fix: Fallback von 'std.7' auf korrekten Key 'std_7' korrigiert
        $templateKey = (string) ($post['template_key'] ?? 'std_7');
        $reason      = \trim((string) ($post['reason'] ?? 'Gutschein'));
        $type        = (string) ($post['voucher_discount_type'] ?? 'free');
        $value       = (float) ($post['voucher_discount_value'] ?? 0.0);
        $isMultiUse  = isset($post['voucher_multi_use']);
        $maxUses     = $isMultiUse ? (int) ($post['voucher_max_uses'] ?? 1) : 1;
        $customCode  = \strtoupper(\trim((string) ($post['voucher_custom_code'] ?? '')));
        $expiresAt   = (string) ($post['voucher_expires_at'] ?? '');
        $dateMode    = (string) ($post['voucher_date_mode'] ?? 'fixed');

        if ($value < 0) {
            throw ValidationException::withMessage('Fehler: Der Rabattwert darf nicht negativ sein.');
        }

        $parzelleRaw    = \trim(\strip_tags((string) ($post['parzelle'] ?? '')));
        $kennzeichenRaw = \trim(\strip_tags((string) ($post['kennzeichen'] ?? '')));

        // FIX: Prefill-Daten vor dem Speichern strikt durch die Value Objects prüfen!
        // Da Prefill optional ist, prüfen wir nur, wenn auch wirklich etwas eingegeben wurde.
        if ($parzelleRaw !== '') {
            new PlotNumber($parzelleRaw);
        }
        if ($kennzeichenRaw !== '') {
            new LicensePlate($kennzeichenRaw);
        }

        $prefillData = [
            'datum_bis'   => $dateMode === 'fixed' ? (string) ($post['datum_bis'] ?? '') : '',
            'datum_von'   => $dateMode === 'fixed' ? (string) ($post['datum_von'] ?? '') : '',
            'firma'       => \trim(\strip_tags((string) ($post['firma'] ?? ''))),
            'kennzeichen' => $kennzeichenRaw,
            'name'        => \trim(\strip_tags((string) ($post['name'] ?? ''))),
            'parzelle'    => $parzelleRaw,
            'typ'         => (string) ($post['typ'] ?? ''),
            'zweck'       => \strip_tags((string) ($post['zweck'] ?? '')),
        ];

        return new self(
            $templateKey,
            $reason,
            $type,
            $value,
            $isMultiUse,
            $maxUses,
            $customCode,
            $expiresAt !== '' ? $expiresAt : null,
            $dateMode,
            $prefillData,
        );
    }
}
