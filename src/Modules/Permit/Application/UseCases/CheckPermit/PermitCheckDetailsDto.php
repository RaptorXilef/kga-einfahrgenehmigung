<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\CheckPermit;

/**
 * Read-Model für die QR-Code Scanner Ansicht (Check).
 * 100% Logikfrei für die View.
 */
final readonly class PermitCheckDetailsDto
{
    public function __construct(
        public bool $isFound,
        public bool $showAdminView,
        public string $pageStateClass,    // is-state-valid, is-state-error, is-state-rest
        public string $statusColorClass,  // u-color-success, u-color-danger, u-color-warning
        public string $statusIcon,        // success.webp, denied.webp, hourglass.webp
        public string $statusHeadline,    // "GENEHMIGUNG GÜLTIG", "ZUTRITT VERWEIGERT", "AKTUELL RUHEZEIT"
        public string $statusSubTextHtml, // "<p>Nächste Einfahrt...</p>"
        public string $code,
        public string $ownerName,
        public string $plotNumber,
        public string $emailHtml,
        public string $purpose,
        public string $vehicleType,
        public string $licensePlate,
        public ?string $company,
        public string $validityPeriod,    // "12.10.2026 bis 14.10.2026"
        public string $openingHoursHtml,
        public string $holidayNoticeHtml,
        public string $priceFormatted,
        public string $createdAtFormatted,
        public string $financeStatusText, // "BEZAHLT", "OFFEN"
        public string $financeStatusClass,// "success", "warning"
        public bool $isSuspended,
        public ?string $suspensionReason,
        public bool $isPaid,
    ) {
    }
}
