<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * Kern-Entität für eine Einfahrgenehmigung.
 *
 * Repräsentiert eine einzelne Genehmigung mit allen relevanten Daten.
 *
 * @file      src/Core/Entity/Permit.php
 *
 * @since     0.1.0
 * - feat(core): Initiale Erstellung der Permit-Entität.
 */

declare(strict_types=1);

namespace App\Core\Entity;

use DateTimeImmutable;

/**
 * Kern-Entität für eine Ausnahmegenehmigung (v0.4.0).
 */
final readonly class Permit
{
    public function __construct(
        public string $code,           // ML-26-0020-X8Y1
        public string $name,
        public string $email,
        public string $parzelle,      // Immer 4-stellig (0020)
        public string $typ,           // pkw, lkw
        public string $kennzeichen,
        public ?string $firma,        // Optional für LKW
        public string $zweck,
        public float $preisSnapshot, // Der Preis zum Zeitpunkt der Buchung / Wichtig für die Finanzstatistik
        public DateTimeImmutable $von,
        public DateTimeImmutable $bis,
        public string $status = 'wartend',
        public ?DateTimeImmutable $erstellt = new DateTimeImmutable(),
    ) {
    }

    /**
     * Prüft die Gültigkeit (v0.4.0: Sofort gültig, Status 'wartend' ist nur intern)
     */
    public function isValid(): bool
    {
        $now = new DateTimeImmutable();

        // v0.4.0: Genehmigung ist sofort gültig, unabhängig vom Zahlungsstatus (Verwaltungsintern)
        return $now >= $this->von && $now <= $this->bis;
    }
}
