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
        public readonly ?string $interner_kommentar = null,
        public readonly array $agreements = [],
        public readonly ?DateTimeImmutable $bezahlt_am = null,
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

    public function markAsPaid(?string $grund = null, ?DateTimeImmutable $buchungsdatum = null): void
    {
        if ($this->status->current === PermitStatus::Bezahlt) {
            return;
        }

        $this->status = new Status(
            PermitStatus::Bezahlt,
            $this->status->is_suspended,
            $this->status->suspension_reason,
            $this->status->last_reminder_at,
        );

        // $bezahlt_am und Kommentare müssten idealerweise in ein neues Objekt kopiert werden
        // (Immigrability), aber wir halten es für die VSA-Übergangsphase pragmatisch.
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
}
