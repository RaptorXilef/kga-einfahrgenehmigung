<?php

declare(strict_types=1);

namespace App\Core\Entity;

/**
 * Werteobjekt / Status-Entität für administrative Workflows.
 * Kapselt den Bezahl- und Verarbeitungsstatus (z.B. 'offen', 'bezahlt') sowie optionale,
 * vom Administrator verhängte Kontroll-Sperren inklusive Begründungstext.
 * Kontext: Workflow- und Lebenszyklussteuerung einer Genehmigung.
 *
 * Repräsentiert den aktuellen Lebenszyklus einer Genehmigung.
 *
 * Path: src/Core/Entity/Status.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class Status
{
    public function __construct(
        public string $current = 'offen',       // technischer Status (offen, bezahlt, storniert)
        public bool $isSuspended = false,         // Manuelle Sperre durch Admin
        public ?string $suspensionReason = null,  // Begründung der Sperre
    ) {
    }
}
