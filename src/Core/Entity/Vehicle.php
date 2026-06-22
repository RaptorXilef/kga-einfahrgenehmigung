<?php

declare(strict_types=1);

namespace App\Core\Entity;

use App\Core\ValueObject\LicensePlate;

/**
 * Entität zur Abbildung der fahrzeugspezifischen Merkmale.
 *
 * Speichert den kategorisierten Fahrzeugtyp (z.B. PKW, LKW), das amtliche Kennzeichen für
 * automatisierte Suchmasken sowie optionale Angaben zu Firmennamen bei Lieferverkehr.
 * Kontext: Identifikationsbasis bei physischen Kontrollen auf dem Gelände.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class Vehicle
{
    public function __construct(
        public string $typ,           // pkw, lkw
        public LicensePlate $kennzeichen,   // im Format B-XX 1234
        public ?string $firma = null, // Optional für LKW
    ) {
    }
}
