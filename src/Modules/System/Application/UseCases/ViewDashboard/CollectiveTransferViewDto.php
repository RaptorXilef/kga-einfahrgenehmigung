<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\ViewDashboard;

/**
 * DTO für manuell zu klärende Sammelüberweisungen aus dem Bank-Import (Finance Tab).
 */
final readonly class CollectiveTransferViewDto
{
    public function __construct(
        public string $id,
        public string $date,
        public string $amountFormatted,
        public string $purpose,
        public string $typeLabel,
        public array $codes,
    ) {
    }
}
