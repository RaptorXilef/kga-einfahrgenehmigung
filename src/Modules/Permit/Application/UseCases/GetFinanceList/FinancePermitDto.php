<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetFinanceList;

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
        public int $overdueLevel, // 0 = Ok, 1 = Mahnfrist, 2 = Überfällig
        public string $deadlineDate,
        public string $daysOverdueText,
        public bool $isOnCooldown,
        public ?string $lastReminderText,
        public bool $isSuspended,
        public ?string $suspensionReason,
    ) {
    }
}
