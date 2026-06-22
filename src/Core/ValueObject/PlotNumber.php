<?php

declare(strict_types=1);

namespace App\Core\ValueObject;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class PlotNumber
{
    public string $value;

    public function __construct(string $value)
    {
        $value = \trim($value);
        if ($value === '') {
            throw new \InvalidArgumentException('Die Parzellennummer darf nicht leer sein.');
        }
        // Auffüllen mit führenden Nullen, falls rein numerisch, auf 4 Stellen
        if (\ctype_digit($value)) {
            // TODO Sollte zusätzlich nicht mehr als 4 Zahlen haben dürfen
            $value = \str_pad($value, 4, '0', \STR_PAD_LEFT);
        }
        $this->value = $value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
