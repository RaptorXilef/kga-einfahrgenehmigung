<?php

declare(strict_types=1);

namespace App\Core\DTO;

use App\Core\Entity\PermitStatus;
use App\Core\ValueObject\EmailAddress;
use App\Core\ValueObject\LicensePlate;
use App\Core\ValueObject\PlotNumber;
use App\Core\ValueObject\Price;
use App\Core\ValueObject\TemplateKey;
use App\Core\ValueObject\VoucherCode;

/**
 * Data Transfer Object bridging the gap between Application boundary and Core domain.
 * Applies Value Objects immediately upon mapping to ensure strict state inside the Core.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class PermitFormData
{
    public function __construct(
        public string $name,
        public ?EmailAddress $email,
        public PlotNumber $parzelle,
        public string $typ,
        public LicensePlate $kennzeichen,
        public ?string $firma,
        public string $zweck,
        public TemplateKey $templateKey,
        public string $datumVon,
        public string $datumBis,
        public Price $manualPrice,
        public PermitStatus $status = PermitStatus::Offen,
        public ?string $internerKommentar = null,
        public array $agreements = [],
        public ?VoucherCode $voucher = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $statusStr  = $data['status'] ?? 'offen';
        $statusEnum = $statusStr instanceof PermitStatus ? $statusStr : (PermitStatus::tryFrom($statusStr) ?? PermitStatus::Offen);

        $emailInput   = \trim($data['email'] ?? '');
        $voucherInput = \trim($data['voucher'] ?? '');

        return new self(
            $data['name'] ?? '',
            $emailInput !== '' ? new EmailAddress($emailInput) : null,
            new PlotNumber($data['parzelle'] ?? ''),
            $data['typ'] ?? 'pkw',
            new LicensePlate($data['kennzeichen'] ?? ''),
            $data['firma'] ?? null,
            $data['zweck'] ?? 'Privat',
            new TemplateKey($data['template_key'] ?? 'std_7'),
            $data['datum_von'] ?? 'now',
            $data['datum_bis'] ?? 'now',
            new Price((float) ($data['manual_price'] ?? ($data['preis'] ?? 0.0))),
            $statusEnum,
            $data['interner_kommentar'] ?? null,
            $data['agreements'] ?? [],
            $voucherInput !== '' ? new VoucherCode($voucherInput) : null,
        );
    }
}
