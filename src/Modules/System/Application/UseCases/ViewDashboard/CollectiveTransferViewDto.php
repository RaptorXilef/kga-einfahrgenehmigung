<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\ViewDashboard;

/**
 * 100% logikfreies View-DTO für manuell zu klärende Überweisungen aus dem Bank-Import (Finance Tab).
 */
final readonly class CollectiveTransferViewDto
{
    /**
     * @param string[] $codes
     * @param array<int, array{extracted: string, dbCode: string}> $codeMappings
     * @param CollectiveTransferPermitItemViewDto[] $relatedPermits
     */
    public function __construct(
        public string $id,
        public string $date,
        public string $amountFormatted,
        public string $currency,
        public bool $hasExpectedAmount,
        public string $expectedAmountFormatted,
        public string $amountDiffBadgeText,
        public string $amountDiffBadgeClass,
        public string $purpose,
        public bool $hasSenderName,
        public string $senderName,
        public string $reasonBadgeText,
        public string $reasonBadgeClass,
        public string $reasonTitle,
        public string $reasonHint,
        public string $typeLabel,
        public array $codes,
        public bool $hasCodeMappings,
        public array $codeMappings,
        public bool $hasRelatedPermits,
        public array $relatedPermits,
    ) {
    }
}
