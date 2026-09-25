<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetPermitByCode;

use DateTimeImmutable;

/**
 * Flaches, unveränderliches CQRS Read-Model für eine einzelne Genehmigung.
 * Ersetzt die Domain-Entity bei allen reinen Lese-Operationen (Check, PDF-Druck, Checkout-Success).
 */
final readonly class PermitReadDto
{
    public function __construct(
        public string $code,
        public string $templateKey,
        public string $ownerName,
        public string $ownerEmail,
        public string $plotNumber,
        public string $vehicleType,
        public string $licensePlate,
        public ?string $company,
        public string $purpose,
        public float $price,
        public string $priceFormatted,
        public DateTimeImmutable $validFrom,
        public DateTimeImmutable $validUntil,
        public string $validFromFormatted,
        public string $validUntilFormatted,
        public DateTimeImmutable $createdAt,
        public string $createdAtFormatted,
        public string $status,
        public bool $isPaid,
        public bool $isSuspended,
        public ?string $suspensionReason,
        public string $usageText,
        public string $paymentDueDateFormatted,
    ) {
    }

    /**
     * Prüft anhand des Read-Models, ob die Genehmigung zum übergebenen Zeitpunkt gültig ist.
     */
    public function isCurrentlyValid(bool $requirePayment, DateTimeImmutable $now): bool
    {
        if ($this->isSuspended || $this->status === 'storniert') {
            return false;
        }

        if ($requirePayment && !$this->isPaid) {
            return false;
        }

        $endOfPeriod = $this->validUntil->setTime(23, 59, 59);

        return $now >= $this->validFrom && $now <= $endOfPeriod;
    }
}
