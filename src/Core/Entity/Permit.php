<?php

declare(strict_types=1);

namespace App\Core\Entity;

use App\Core\ValueObject\PermitCode;
use App\Core\ValueObject\TemplateKey;
use DateTimeImmutable;

/**
 * Haupt-Aggregatwurzel (Entity) für eine Einfahr-/Ausnahme-Genehmigung.
 *
 * Verknüpft den eindeutigen Systemcode, das zugrundeliegende Tarif-Template, den Besitzer,
 * das Fahrzeug, den Gültigkeitszeitraum sowie den aktuellen Bezahl- und Sperrstatus.
 * Kontext: Zentrales Datenmodell für sämtliche Validierungs-, Prüf- und Abrechnungsprozesse.
 *
 * Repräsentiert eine einzelne Genehmigung mit allen relevanten Daten.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class Permit
{
    public function __construct(
        public PermitCode $code,                                             // ML-26-0020-X8Y1
        public TemplateKey $template_key,                                    // Welches Template wurde genutzt?
        public Owner $owner,
        public Vehicle $vehicle,
        public Validity $validity,
        public Status $status,
        public DateTimeImmutable $erstellt = new DateTimeImmutable(),
        public ?string $interner_kommentar = null,                          // Für manuelle Buchung
        public array $agreements = [],
        public ?Status $state = null,                                       // Kompatibilitäts-Dummy falls genutzt
        public ?DateTimeImmutable $bezahlt_am = null,                       // Separates Bezahldatum
    ) {
    }

    /**
     * Ermittelt die aktuelle zeitliche und administrative Gültigkeit der Genehmigung.
     * Prüft, ob das Ticket manuell gesperrt (Suspended) wurde und ob sich die aktuelle
     * Systemzeit innerhalb des definierten Von-Bis-Fensters (bis 23:59:59 Uhr des Endtages) befindet.
     *
     * @return bool True, wenn die Genehmigung jetzt aktiv und für Kontrollen gültig ist.
     */
    public function isValid(bool $requirePayment = false): bool
    {
        $now = new DateTimeImmutable();

        // 1. Check: Manuell gesperrt?
        if ($this->status->is_suspended) {
            return false;
        }

        // Zahlungsstatus prüfen, falls gefordert
        if ($requirePayment && $this->status->current !== PermitStatus::Bezahlt) {
            return false;
        }

        // 2. Zeitlicher Check:
        // Wir setzen das Enddatum für den Vergleich auf den letzten Moment des Tages (3:59:59 Uhr).
        $endOfPeriod = $this->validity->bis->setTime(23, 59, 59);

        return $now >= $this->validity->von && $now <= $endOfPeriod;
    }

    public function isExpired(DateTimeImmutable $now): bool
    {
        return $this->validity->bis < $now;
    }

    public function isFuture(DateTimeImmutable $now): bool
    {
        return $this->validity->von > $now;
    }

    public function isPaid(): bool
    {
        return $this->status->current === PermitStatus::Bezahlt;
    }

    public function isSuspended(): bool
    {
        return $this->status->is_suspended;
    }

    public function getStatus(): PermitStatus
    {
        return $this->status->current;
    }

    // --- Owner Delegation ---
    // TODO DOCBLOCK
    public function getOwnerName(): string
    {
        return $this->owner->name;
    }

    public function getOwnerEmail(): string
    {
        return $this->owner->email ? $this->owner->email->value : '';
    }

    public function getPlotNumber(): string
    {
        return $this->owner->parzelle->getFormatted();
    }

    public function getVehicleType(): string
    {
        return $this->vehicle->typ;
    }

    public function getLicensePlate(): string
    {
        return $this->vehicle->kennzeichen->value;
    }

    public function getCompany(): ?string
    {
        return $this->vehicle->firma;
    }

    public function getPrice(): float
    {
        return $this->validity->preis->value;
    }

    public function getPurpose(): string
    {
        return $this->validity->zweck;
    }

    public function getValidFrom(): DateTimeImmutable
    {
        return $this->validity->von;
    }

    public function getValidUntil(): DateTimeImmutable
    {
        return $this->validity->bis;
    }

    public function getSuspensionReason(): ?string
    {
        return $this->status->suspension_reason;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->erstellt;
    }

    /**
     * TODO DOCBLOCK
     * Prüft, ob diese Genehmigung mit einem Suchbegriff übereinstimmt.
     */
    public function matchesSearch(string $queryLower): bool
    {
        if ($queryLower === '') {
            return true;
        }

        $emailStr = $this->owner->email ? $this->owner->email->value : '';

        // Suche nutzt das formatierte "0036" Format, damit Suchen nach "0036" klappen.
        $searchString = \strtolower(
            $this->code->value . ' ' .
                $this->owner->name . ' ' .
                $emailStr . ' ' .
                $this->vehicle->kennzeichen->value . ' ' .
                $this->owner->parzelle->getFormatted() . ' ' .
                $this->validity->zweck,
        );

        return \str_contains($searchString, $queryLower);
    }

    /**
     * TODO DOCBLOCK
     * Prüft, ob diese Genehmigung mit einer bestimmten Parzelle und einem Zeitraum kollidiert.
     *
     * Wir vergleichen jetzt saubere INTs!
     */
    public function hasCollision(int $parzelleId, DateTimeImmutable $start, DateTimeImmutable $end): bool
    {
        if ($this->owner->parzelle->value !== $parzelleId) {
            return false;
        }

        return $this->validity->von <= $end && $this->validity->bis >= $start;
    }
}
