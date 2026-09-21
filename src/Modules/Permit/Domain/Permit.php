<?php

declare(strict_types=1);

namespace App\Modules\Permit\Domain;

use App\SharedKernel\Domain\ValueObject\PermitCode;
use App\SharedKernel\Domain\ValueObject\TemplateKey;
use DateTimeImmutable;

/**
 * Die absolute Kern-Entität unseres Systems.
 * Behält ihren internen State konsequent bei und schützt ihn.
 */
final class Permit
{
    public function __construct(
        public readonly PermitCode $code,
        public readonly TemplateKey $template_key,
        public readonly Owner $owner,
        public readonly Vehicle $vehicle,
        public readonly Validity $validity,
        private Status $status,
        public readonly DateTimeImmutable $erstellt,
        public ?string $interner_kommentar = null,
        public readonly array $agreements = [],
        public ?DateTimeImmutable $bezahlt_am = null,
    ) {
    }

    public function isValid(bool $requirePayment = false): bool
    {
        $now = new DateTimeImmutable();

        if ($this->status->is_suspended) {
            return false;
        }

        if ($requirePayment && $this->status->current !== PermitStatus::Bezahlt) {
            return false;
        }

        $endOfPeriod = $this->validity->bis->setTime(23, 59, 59);

        return $now >= $this->validity->von && $now <= $endOfPeriod;
    }

    public function markAsPaid(?string $kommentar = null, ?DateTimeImmutable $buchungsdatum = null): void
    {
        $this->status = new Status(
            PermitStatus::Bezahlt,
            $this->status->is_suspended,
            $this->status->suspension_reason,
            $this->status->last_reminder_at,
        );

        $this->bezahlt_am = $buchungsdatum;
        $this->interner_kommentar = $kommentar;
    }

    public function suspend(string $reason): void
    {
        $this->status = new Status($this->status->current, true, $reason, $this->status->last_reminder_at);
    }

    public function unsuspend(): void
    {
        $this->status = new Status($this->status->current, false, null, $this->status->last_reminder_at);
    }

    public function getStatus(): PermitStatus
    {
        return $this->status->current;
    }

    public function isSuspended(): bool
    {
        return $this->status->is_suspended;
    }

    public function getSuspensionReason(): ?string
    {
        return $this->status->suspension_reason;
    }

    // Legacy Getter / Wrapper (Vorrübergehend für Actions)
    public function getOwnerName(): string
    {
        return $this->owner->name;
    }

    public function getPlotNumber(): string
    {
        return $this->owner->parzelle->getFormatted();
    }

    public function getOwnerEmail(): string
    {
        return $this->owner->email ? $this->owner->email->value : '';
    }

    public function getLicensePlate(): string
    {
        return $this->vehicle->kennzeichen->value;
    }

    public function getVehicleType(): string
    {
        return $this->vehicle->typ;
    }

    public function getCompany(): ?string
    {
        return $this->vehicle->firma;
    }

    public function getPurpose(): string
    {
        return $this->validity->zweck;
    }

    public function getPrice(): float
    {
        return $this->validity->preis->value;
    }

    public function getValidFrom(): DateTimeImmutable
    {
        return $this->validity->von;
    }

    public function getValidUntil(): DateTimeImmutable
    {
        return $this->validity->bis;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->erstellt;
    }

    public function isPaid(): bool
    {
        return $this->status->current === PermitStatus::Bezahlt;
    }

    public function isExpired(DateTimeImmutable $now): bool
    {
        return $this->validity->bis < $now;
    }

    public function isFuture(DateTimeImmutable $now): bool
    {
        return $this->validity->von > $now;
    }
}
