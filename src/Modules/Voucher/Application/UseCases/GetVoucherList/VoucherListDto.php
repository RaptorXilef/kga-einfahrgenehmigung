<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Application\UseCases\GetVoucherList;

/**
 * 100% View-spezifisches DTO.
 * Enthält fertige Strings, URLs und CSS-Klassen für das PHTML-Template.
 */
final readonly class VoucherListDto
{
    public function __construct(
        public string $code,
        public string $redeemUrl,
        public string $reason,
        public bool $isInvalid,         // Für die ausgegraute Zeile
        public string $rowClass,        // c-table__row--danger u-opacity-50 oder leer
        public string $discountText,    // z.B. "100% Rabatt"
        public string $discountBadgeClass,
        public string $usageBadgeText,  // z.B. "Mehrfach (0/10)"
        public ?string $usageBadgeIcon, // z.B. "sync.webp"
        public string $dateModeText,    // "Flexible Datenwahl"
        public ?string $prefilledName,
        public string $prefilledPlot,   // Vorberechneter Parzellen-String (inkl. '?' Fallback)
        public ?string $expiresText,    // "Gültig bis: 12.12.2026 Uhr"
        public bool $isDeactivated,
        public string $toggleActionUrl, // "activate_voucher"
        public string $toggleButtonClass,  // "c-button--success" oder "c-button--danger"
        public string $toggleIcon,      // "unlock.webp"
        public string $toggleTitle,      // "Aktivieren"
    ) {
    }
}
