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

final readonly class Permit
{
    public function __construct(
        public string $code,
        public string $name,
        public string $email,
        public string $kennzeichen,
        public string $parzelle,
        public string $typ,   // PKW, Transporter, LKW
        public string $zweck, // Grund der Einfahrt
        public DateTimeImmutable $von,
        public DateTimeImmutable $bis,
        public string $status = 'wartend',
        public ?DateTimeImmutable $erstellt = new DateTimeImmutable(),
    ) {
    }

    /**
     * Prüft, ob die Genehmigung zum jetzigen Zeitpunkt gültig ist.
     */
    public function isValid(): bool
    {
        $now = new DateTimeImmutable('today');

        return $this->status === 'bezahlt'
            && $now >= $this->von
            && $now <= $this->bis;
    }
}
