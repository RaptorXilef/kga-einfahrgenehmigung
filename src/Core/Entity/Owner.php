<?php

declare(strict_types=1);

namespace App\Core\Entity;

use App\Core\ValueObject\EmailAddress;
use App\Core\ValueObject\PlotNumber;

/**
 * Represents the physical owner / tenant of a plot.
 *
 * Entität für die Stammdaten des Antragstellers/Besitzers.
 *
 * Bildet die persönlichen Daten wie Name, E-Mail-Adresse und die zugehörige
 * Parzellennummer im Vereinsgelände ab.
 * Kontext: Kernkomponente zur Zuordnung und Identifizierung von Genehmigungsinhabern.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class Owner
{
    public function __construct(
        public string $name,            // Name des Nutzers
        public ?EmailAddress $email,    // E-Mail-Adresse des Nutzers
        public PlotNumber $parzelle,    // Immer 4-stellig (0020)
    ) {
    }
}
