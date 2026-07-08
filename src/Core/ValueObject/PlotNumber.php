<?php

declare(strict_types=1);

namespace App\Core\ValueObject;

/**
 * Value Object representing a garden plot number (Parzellennummer).
 *
 * Enforces strict numeric values, > 0, and consistent zero-padding.
 */
final readonly class PlotNumber
{
    /**
     * @var string The normalized, 4-digit plot number string.
     */
    public string $value;

    /**
     * @param  int|string                $value The raw plot number input.
     * @throws \InvalidArgumentException If the plot number is empty, not numeric, or <= 0.
     */
    public function __construct(int|string $value)
    {
        $valStr = \trim((string) $value);

        if ($valStr === '') {
            throw new \InvalidArgumentException('Die Parzellennummer darf nicht leer sein.');
        }

        if (! \ctype_digit($valStr)) {
            throw new \InvalidArgumentException('Fehler: Die Parzellennummer darf ausschließlich aus Zahlen bestehen.');
        }

        $intVal = (int) $valStr;

        if ($intVal <= 0) {
            throw new \InvalidArgumentException('Fehler: Die Parzellennummer muss größer als 0 sein.');
        }

        // Wir speichern den Wert als 4-stelligen String,
        // um 100% abwärtskompatibel mit der bestehenden Datenbank zu bleiben.
        $this->value = \str_pad((string) $intVal, 4, '0', \STR_PAD_LEFT);
    }

    /**
     * Returns the plot number as a strict integer (useful for range comparisons).
     */
    public function toInt(): int
    {
        return (int) $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
