<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\SubmitPermitRequest;

/**
 * Striktes View-DTO für das öffentliche Antragsformular.
 * Befreit das PHTML Template von jeglicher Fallback- und Business-Logik.
 */
final readonly class PermitFormViewDto
{
    /**
     * @param array<string, mixed> $agreementsChecked
     * @param array<int, array{value: string, label: string, selected: bool, selectedAttr: string}> $templateOptions
     * @param array<int, array{value: string, label: string, selected: bool, selectedAttr: string}> $vehicleOptions
     * @param array<int, array{value: string, label: string, selected: bool, selectedAttr: string}> $purposeOptions
     * @param array<string, array{label_html: string, required: bool, requiredAttr: string, checkedAttr: string}> $agreements
     */
    public function __construct(
        public string $name,
        public bool $isNameLocked,
        public string $nameReadonlyAttr,
        public string $email,
        public bool $isEmailLocked,
        public string $emailReadonlyAttr,
        public string $parzelle,
        public bool $isParzelleLocked,
        public string $parzelleReadonlyAttr,
        public string $typ,
        public bool $isTypLocked,
        public string $typReadonlyAttr,
        public string $kennzeichen,
        public bool $isKennzeichenLocked,
        public string $kennzeichenReadonlyAttr,
        public string $firma,
        public bool $isFirmaLocked,
        public string $firmaReadonlyAttr,
        public string $zweck,
        public bool $isZweckLocked,
        public string $zweckReadonlyAttr,
        public string $templateKey,
        public bool $isTemplateKeyLocked,
        public string $templateKeyReadonlyAttr,
        public string $datumVon,
        public bool $isDatumVonLocked,
        public string $datumVonReadonlyAttr,
        public string $datumBis,
        public bool $isDatumBisLocked,
        public string $datumBisReadonlyAttr,
        public string $voucherInput,
        public string $voucherCode,
        public string $voucherReason,
        public bool $hasActiveVoucher,
        public string $submitButtonText,
        public bool $hasMultipleTemplates,
        public string $singleTemplateKey,
        public array $agreementsChecked,
        // Fertig aufbereitete Arrays für die Select-Felder und Checkboxen
        public array $templateOptions,
        public array $vehicleOptions,
        public array $purposeOptions,
        public array $agreements,
        public string $tplMetadataJson, // Frontend-Config ohne Inline-Logik
    ) {
    }
}
