<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetPermitHistory;

/**
 * 100% logikfreies View-DTO für die Darstellung im Pächter-Verlauf.
 */
final readonly class HistoryPermitViewDto
{
    public function __construct(
        public string $code,
        public string $ownerName,
        public string $plotNumber,
        public string $vehicleIcon,
        public string $vehicleIconClass,
        public string $vehicleLabel,
        public string $licensePlate,
        public string $validFromDate,
        public string $validUntilDate,
        public bool $isExpired,
        public string $rowClass,
        public string $countdownText,
        public string $countdownBadgeClass,
        public string $statusText,
        public string $statusBadgeClass,
        public bool $canCancel,
        public string $createdAtTimestamp,
    ) {
    }
}
