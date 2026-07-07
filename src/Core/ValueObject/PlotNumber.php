<?php

declare(strict_types=1);

namespace App\Core\ValueObject;

/**
 * Value Object representing a garden plot number (Parzellennummer).
 *
 * Enforces zero-padding for numeric plots and immutability.
 */
final readonly class PlotNumber
{
    /**
     * @var string The normalized plot number.
     */
    public string $value;

    /**
     * @param  string                    $value The raw plot number input.
     * @throws \InvalidArgumentException If the plot number is empty.
     */
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

    /**
     * Returns the normalized plot number as a string.
     */
    public function __toString(): string
    {
        return $this->value;
    }
}
