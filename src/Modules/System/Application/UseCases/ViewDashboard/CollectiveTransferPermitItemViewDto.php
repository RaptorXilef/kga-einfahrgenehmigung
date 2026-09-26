<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\ViewDashboard;

/**
 * 100% logikfreies View-DTO für eine zugehörige Genehmigung (offen, bezahlt oder storniert)
 * innerhalb einer Bank-Import-Prüfkarte (Betrugs- & Kontext-Übersicht).
 */
final readonly class CollectiveTransferPermitItemViewDto
{
    public function __construct(
        public string $code,
        public string $shortCode,
        public string $extractedHint,
        public bool $hasExtractedHint,
        public bool $isDirectMatch,
        public string $rowHighlightClass,
        public string $relationLabel,
        public string $relationBadgeClass,
        public string $ownerName,
        public string $plotFormatted,
        public string $vehicleType,
        public string $licensePlate,
        public string $validityPeriod,
        public string $createdAtFormatted,
        public string $priceFormatted,
        public string $statusBadgeText,
        public string $statusBadgeClass,
        public bool $canMarkAsPaid,
    ) {
    }
}
