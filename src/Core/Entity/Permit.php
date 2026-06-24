<?php

declare(strict_types=1);

namespace App\Core\Entity;

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
        public string $code,        // ML-26-0020-X8Y1
        public string $template_key, // Welches Template wurde genutzt?
        public Owner $owner,
        public Vehicle $vehicle,
        public Validity $validity,
        public Status $status,
        public \DateTimeImmutable $erstellt = new \DateTimeImmutable(),
        public ?string $interner_kommentar = null, // Für manuelle Buchung
        public array $agreements = [],
        public ?Status $state = null, // Kompatibilitäts-Dummy falls genutzt
        public ?\DateTimeImmutable $bezahlt_am = null, // Separates Bezahldatum
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
        $now = new \DateTimeImmutable();

        // 1. Check: Manuell gesperrt?
        if ($this->status->is_suspended) {
            return false;
        }

        // Zahlungsstatus prüfen, falls gefordert
        if ($requirePayment && \strtolower($this->status->current) !== 'bezahlt') {
            return false;
        }

        // 2. Zeitlicher Check:
        // Wir setzen das Enddatum für den Vergleich auf den letzten Moment des Tages (3:59:59 Uhr).
        $endOfPeriod = $this->validity->bis->setTime(23, 59, 59);

        return $now >= $this->validity->von && $now <= $endOfPeriod;
    }

    // TODO Reihenfolge der Methoden optimieren/sortieren und ggf. mit Kommentaren in Kategorien unterteilen.
    // TODO DOCBLOCK
    public function isExpired(\DateTimeImmutable $now): bool
    {
        return $this->validity->bis < $now;
    }

    // TODO DOCBLOCK
    public function isFuture(\DateTimeImmutable $now): bool
    {
        return $this->validity->von > $now;
    }

    // TODO DOCBLOCK
    public function isPaid(): bool
    {
        return \strtolower(\trim($this->status->current)) === 'bezahlt';
    }

    // TODO DOCBLOCK
    public function isSuspended(): bool
    {
        return $this->status->is_suspended;
    }

    // TODO DOCBLOCK
    public function getStatus(): string
    {
        return $this->status->current;
    }

    // --- Owner Delegation ---
    // TODO DOCBLOCK
    public function getOwnerName(): string
    {
        return $this->owner->name;
    }

    // TODO DOCBLOCK
    public function getOwnerEmail(): string
    {
        return $this->owner->email->value;
    }

    // TODO DOCBLOCK
    public function getPlotNumber(): string
    {
        return $this->owner->parzelle->value;
    }

    // --- Vehicle Delegation ---
    // TODO DOCBLOCK
    public function getVehicleType(): string
    {
        return $this->vehicle->typ;
    }

    // TODO DOCBLOCK
    public function getLicensePlate(): string
    {
        return $this->vehicle->kennzeichen->value;
    }

    // TODO DOCBLOCK
    public function getCompany(): ?string
    {
        return $this->vehicle->firma;
    }

    // --- Validity Delegation ---
    // TODO DOCBLOCK
    public function getPrice(): float
    {
        return $this->validity->preis;
    }

    // TODO DOCBLOCK
    public function getPurpose(): string
    {
        return $this->validity->zweck;
    }

    // TODO DOCBLOCK
    public function getValidFrom(): \DateTimeImmutable
    {
        return $this->validity->von;
    }

    // TODO DOCBLOCK
    public function getValidUntil(): \DateTimeImmutable
    {
        return $this->validity->bis;
    }

    // TODO DOCBLOCK
    public function getSuspensionReason(): ?string
    {
        return $this->status->suspension_reason;
    }

    // TODO DOCBLOCK
    public function getCreatedAt(): \DateTimeImmutable
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

        // Da wir IN der Entität sind, dürfen wir die Eigenschaften direkt lesen.
        $searchString = \strtolower(
            $this->code . ' ' .
            $this->owner->name . ' ' .
            $this->owner->email->value . ' ' .
            $this->vehicle->kennzeichen->value . ' ' .
            $this->owner->parzelle->value . ' ' .
            $this->validity->zweck,
        );

        return \str_contains($searchString, $queryLower);
    }

    /**
     * TODO DOCBLOCK
     * Prüft, ob diese Genehmigung mit einer bestimmten Parzelle und einem Zeitraum kollidiert.
     */
    public function hasCollision(string $parzelleFormatted, \DateTimeImmutable $start, \DateTimeImmutable $end): bool
    {
        if ($this->owner->parzelle->value !== $parzelleFormatted) {
            return false;
        }

        // Mathematischer Überschneidungs-Check (StartA <= EndB && EndA >= StartB)
        return $this->validity->von <= $end && $this->validity->bis >= $start;
    }
}
