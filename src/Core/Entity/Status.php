<?php

declare(strict_types=1);

namespace App\Core\Entity;

use DateTimeImmutable;

/**
 * Werteobjekt / Status-Entität für administrative Workflows.
 * Kapselt den Bezahl- und Verarbeitungsstatus (z.B. 'offen', 'bezahlt') sowie optionale,
 * vom Administrator verhängte Kontroll-Sperren inklusive Begründungstext.
 * Kontext: Workflow- und Lebenszyklussteuerung einer Genehmigung.
 *
 * Repräsentiert den aktuellen Lebenszyklus einer Genehmigung.
 */
final readonly class Status
{
    public function __construct(
        public PermitStatus $current = PermitStatus::Offen, // technischer Status (offen, bezahlt, storniert)
        public bool $is_suspended = false,                  // Manuelle Sperre durch Admin
        public ?string $suspension_reason = null,           // Begründung der Sperre
        public ?DateTimeImmutable $last_reminder_at = null, // Zahlungserinnerung senden
    ) {
    }
}
