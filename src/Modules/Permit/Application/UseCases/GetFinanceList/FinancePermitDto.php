<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetFinanceList;

/**
 * 100% View-spezifisches Read-Model für die Finanz-Tabelle.
 */
final readonly class FinancePermitDto
{
    public function __construct(
        public string $code,
        public string $ownerName,
        public string $plotNumber,
        public string $licensePlate,
        public string $vehicleIcon,
        public string $vehicleLabel,
        public string $emailHtml,
        public float $priceRaw,
        public string $priceFormatted,
        public string $rowClass,
        public string $deadlineDate,
        public int $deadlineTimestamp,
        public string $deadlineBadgeClass,
        public ?string $deadlineIcon,
        public string $deadlineText,
        public ?string $reminderClass,
        public ?string $reminderText,
        public string $reminderButtonClass,
        public string $reminderButtonTitle,
        public string $sortSuspendedValue,
        public bool $isOnCooldown,
        public bool $isSuspended,
        public ?string $suspensionReason,
    ) {
    }
}
