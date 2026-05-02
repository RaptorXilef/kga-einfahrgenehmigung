<?php

declare(strict_types=1);

namespace App\Core\Entity;

final readonly class Validity
{
    public function __construct(
        public \DateTimeImmutable $von,
        public \DateTimeImmutable $bis,
        public float $preisSnapshot, // Der Preis zum Zeitpunkt der Buchung / Wichtig für die Finanzstatistik
        public string $zweck,
    ) {
    }
}
