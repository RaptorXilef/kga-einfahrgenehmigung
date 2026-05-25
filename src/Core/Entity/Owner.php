<?php

declare(strict_types=1);

namespace App\Core\Entity;

/**
 * Entität für die Stammdaten des Antragstellers/Besitzers.
 *
 * Bildet die persönlichen Daten wie Name, E-Mail-Adresse und die zugehörige
 * Parzellennummer im Vereinsgelände ab.
 * Kontext: Kernkomponente zur Zuordnung und Identifizierung von Genehmigungsinhabern.
 *
 * Path: src/Core/Entity/Owner.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class Owner
{
    public function __construct(
        public string $name,     // Name des Nutzers
        public string $email,    // E-Mail-Adresse des Nutzers
        public string $parzelle, // Immer 4-stellig (0020)
    ) {
    }
}
