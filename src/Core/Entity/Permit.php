<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

// Path: src/Core/Entity/Permit.php

declare(strict_types=1);

namespace App\Core\Entity;

/**
 * Kern-Entität für eine Einfahr-/Ausnahmegenehmigung.
 *
 * Repräsentiert eine einzelne Genehmigung mit allen relevanten Daten.
 */
final readonly class Permit
{
    public function __construct(
        public string $code,        // ML-26-0020-X8Y1
        public string $templateKey, // Welches Template wurde genutzt?
        public Owner $owner,
        public Vehicle $vehicle,
        public Validity $validity,
        public Status $status,
        public \DateTimeImmutable $erstellt = new \DateTimeImmutable(),
        public ?string $internerKommentar = null, // Für manuelle Buchung
    ) {
    }

    /**
     * Prüft die Gültigkeit (v0.4.0: Sofort gültig, Status 'wartend' ist nur intern)
     */
    public function isValid(): bool
    {
        $now = new \DateTimeImmutable();

        // Prüfung über die neuen Value Objects
        return ! $this->status->isSuspended
            && $now >= $this->validity->von
            && $now <= $this->validity->bis;
    }
}
