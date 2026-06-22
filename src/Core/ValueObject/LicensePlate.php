<?php

declare(strict_types=1);

namespace App\Core\ValueObject;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class LicensePlate
{
    public string $value;

    public function __construct(string $plate)
    {
        $this->value = $this->format($plate);
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function getCleanForSearch(): string
    {
        return \preg_replace('/[^A-Z0-9]/', '', \strtoupper($this->value)) ?? '';
    }

    private function format(string $plate): string
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
