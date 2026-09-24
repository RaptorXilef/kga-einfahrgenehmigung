<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\SubmitPermitRequest;

/**
 * Striktes View-DTO für das öffentliche Antragsformular.
 * Befreit das PHTML Template von jeglicher Fallback- und Business-Logik.
 */
final readonly class PermitFormViewDto
{
    public function __construct(
        public string $name,
        public bool $isNameLocked,
        public string $email,
        public bool $isEmailLocked,
        public string $parzelle,
        public bool $isParzelleLocked,
        public string $typ,
        public bool $isTypLocked,
        public string $kennzeichen,
        public bool $isKennzeichenLocked,
        public string $firma,
        public bool $isFirmaLocked,
        public string $zweck,
        public bool $isZweckLocked,
        public string $templateKey,
        public bool $isTemplateKeyLocked,
        public string $datumVon,
        public bool $isDatumVonLocked,
        public string $datumBis,
        public bool $isDatumBisLocked,
        public string $voucherInput,
        public string $voucherCode,
        public string $voucherReason,
        public bool $hasActiveVoucher,
        public array $agreementsChecked,
        // NEU: Fertig aufbereitete Arrays für die Select-Felder und Checkboxen
        public array $templateOptions,
        public array $vehicleOptions,
        public array $purposeOptions,
        public array $agreements,
    ) {
    }
}
