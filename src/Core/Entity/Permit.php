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
 * Path: src/Core/Entity/Permit.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
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
}
