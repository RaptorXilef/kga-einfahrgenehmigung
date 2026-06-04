<?php

declare(strict_types=1);

namespace App\Core\Service;

/**
 * Service zur Formatierung und Normalisierung von deutschen KFZ-Kennzeichen.
 *
 * Path: src/Core/Service/LicensePlateFormatter.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class LicensePlateFormatter
{
    /**
     * Formatiert und normalisiert rohe Kennzeichen-Eingaben in ein standardisiertes, deutsches Kennzeichenformat.
     * Bereinigt Sonderzeichen, trennt Ortskennungen per Bindestrich ab und setzt Leerzeichen vor die Erkennungsnummer
     * (inkl. Berücksichtigung von E- und H-Kennzeichen sowie Sonderregeln für Berlin 'B').
     *
     * Formatiert Kennzeichen (z.B. BHD7398 -> B-HD 7398).
     * Erkennt manuelle Bindestriche und unterstützt 4-er Blöcke (LL-LL).
     * Unterstützt jetzt auch E- und H-Zusätze am Ende.
     *
     * @param string $plate Die rohe Benutzereingabe (z.B. "b-mw1234e" oder "M  XY 999").
     *
     * @return string Das sauber formatierte Kennzeichen (z.B. "B-MW 1234E" oder "M-XY 999").
     */
    public function format(string $plate): string
    {
        $original = \trim(\strtoupper($plate));
        if ($original === '') {
            return '';
        }

        // 1. Wenn der Nutzer bereits ein Minus gesetzt hat -> Automatik deaktivieren
        if (\str_contains($original, '-')) {
            return (string) \preg_replace('/([A-Z])(\d)/', '$1 $2', $original);
        }

        // 2. Komplettreinigung für die Automatik
        $val = (string) \preg_replace('/[^A-Z0-9]/', '', $original);

        // 3. Sonderfall: 4 Buchstaben am Anfang (z.B. BBDW123E -> BB-DW 123E)
        if (\preg_match('/^([A-Z]{2})([A-Z]{2})(\d{1,4}[E|H]?)$/', $val, $matches)) {
            return "{$matches[1]}-{$matches[2]} {$matches[3]}";
        }

        // 4. Berlin-Priorität (B-XX 1234E)
        if (\preg_match('/^(B)([A-Z]{1,2})(\d{1,4}[E|H]?)$/', $val, $matches)) {
            return "{$matches[1]}-{$matches[2]} {$matches[3]}";
        }

        // 5. Standard: 1-3 Buchstaben + 1-2 Buchstaben + Zahlen (+E/H)
        if (\preg_match('/^([A-Z]{1,3})([A-Z]{1,2})(\d{1,4}[E|H]?)$/', $val, $matches)) {
            return "{$matches[1]}-{$matches[2]} {$matches[3]}";
        }

        // 6. Fallback
        return (string) \preg_replace('/^([A-Z]{1,3})(\d{1,4}[E|H]?)$/', '$1 $2', $val);
    }
}
