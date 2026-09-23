<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\FinalizePermit;

final readonly class CheckoutSuccessViewDto
{
    public function __construct(
        public string $permitCode,
        public string $method,
        public bool $isPaid,
        public bool $requirePayment,
        public string $dueDate,
        public string $epcData,
        public string $preisFormatted,
        public string $kontoinhaber,
        public string $iban,
        public string $bic,
        public string $usage,
        public string $ownerEmail,
    ) {
    }
}
