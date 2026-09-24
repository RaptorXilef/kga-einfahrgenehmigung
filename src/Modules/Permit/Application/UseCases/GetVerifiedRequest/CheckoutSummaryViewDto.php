<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetVerifiedRequest;

final readonly class CheckoutSummaryViewDto
{
    public function __construct(
        public string $token,
        public bool $isPayPalEnabled,
        public string $paypalClientId,
        public string $name,
        public string $email,
        public string $parzelle,
        public string $typLabel,
        public string $kennzeichen,
        public string $firma,
        public string $zweckLabel,
        public string $datumVon,
        public string $datumBis,
        public float $preisRaw,
        public string $preisFormatted,
        public string $openingHoursHtml,
        public string $holidayNoticeHtml,
    ) {
    }
}
