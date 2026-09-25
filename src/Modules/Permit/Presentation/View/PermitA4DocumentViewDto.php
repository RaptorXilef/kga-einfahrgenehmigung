<?php

declare(strict_types=1);

namespace App\Modules\Permit\Presentation\View;

/**
 * 100% logikfreies View-DTO für das A4-Genehmigungsdokument (templates/emails/permit_a4_document.phtml).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class PermitA4DocumentViewDto
{
    /**
     * @param array<int, array{label: string, cssClass: string}> $quarters
     */
    public function __construct(
        public string $fullIdentifier,
        public string $headerColor,
        public string $titleHtml,
        public string $vereinsName,
        public bool $isPermanent,
        public array $quarters,
        public string $checkQrBase64,
        public string $openingTextHtml,
        public string $holidayNoticeHtml,
        public string $name,
        public string $displayVon,
        public string $displayBis,
        public string $kennzeichen,
        public string $firma,
        public string $parzelle,
        public string $zweck,
        public string $conditionValidityHtml,
        public string $conditionParkingHtml,
        public string $terminkalenderUrl,
        public string $erstellt,
        public string $baseUrl,
    ) {
    }
}
