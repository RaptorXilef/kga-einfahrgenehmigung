<?php

declare(strict_types=1);

namespace App\Core\Entity;

/**
 * Werteobjekt für den zeitlichen Rahmen und die finanziellen Konditionen.
 *
 * Definiert den genauen Gültigkeitsbereich (Start- und Enddatum), den Verwendungszweck
 * der Einfahrt sowie einen unveränderlichen Preis-Snapshot zum Zeitpunkt der Buchung.
 * Kontext: Revisionssichere Abrechnungsbasis und zeitliche Validierungsgrundlage.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class Validity
{
    public function __construct(
        public \DateTimeImmutable $von,
        public \DateTimeImmutable $bis,
        public float $preis, // Der Preis zum Zeitpunkt der Buchung / Wichtig für die Finanzstatistik
        public string $zweck,
    ) {
    }
}
