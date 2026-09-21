<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\DTO;

use App\Modules\Permit\Domain\PermitStatus;
use App\SharedKernel\Domain\ValueObject\EmailAddress;
use App\SharedKernel\Domain\ValueObject\LicensePlate;
use App\SharedKernel\Domain\ValueObject\PlotNumber;
use App\SharedKernel\Domain\ValueObject\Price;
use App\SharedKernel\Domain\ValueObject\TemplateKey;
use App\SharedKernel\Domain\ValueObject\VoucherCode;

/**
 * Data Transfer Object bridging the gap between Application boundary and Core domain.
 * Applies Value Objects immediately upon mapping to ensure strict state inside the Core.
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
        $statusStr = $data['status'] ?? 'offen';
        $statusEnum = $statusStr instanceof PermitStatus ? $statusStr : (PermitStatus::tryFrom($statusStr) ?? PermitStatus::Offen);

        $emailInput = \trim($data['email'] ?? '');
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
