<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetDashboardPermits;

/**
 * 100% View-spezifisches Read-Model für die Dashboard-Tabellen.
 */
final readonly class DashboardPermitDto
{
    public function __construct(
        public string $code,
        public string $ownerName,
        public string $plotNumber,
        public string $licensePlate,
        public string $vehicleIcon,
        public string $vehicleLabel,
        public string $emailHtml,
        public string $validFromDate,
        public string $validUntilDate,
        public string $createdAtDate,
        public string $createdAtTime,
        public string $priceFormatted,
        public string $rowClass,
        public string $countdownText,
        public string $countdownBadgeClass,
        public string $statusBadgeHtml,
        public bool $showSuspendForm,
        public bool $isSuspended,
        public string $suspendIcon,
        public string $suspendTitle,
        public string $suspendActionUrl,
        public bool $isAnonymized,
    ) {
    }
}
