<?php

declare(strict_types=1);

namespace App\Core\ValueObject;

/**
 * Value Object representing a garden plot number (Parzellennummer).
 *
 * Enforces strict integer types internally.
 */
final readonly class PlotNumber
{
    /**
     * @var int The pure numeric plot number.
     */
    public int $value;

    /**
     * @param  int|string                $value The raw plot number input.
     * @throws \InvalidArgumentException If the plot number is empty, not numeric, or <= 0.
     */
    public function __construct(int|string $value)
    {
        if (\is_string($value)) {
            $value = \trim($value);
            if ($value === '') {
                throw new \InvalidArgumentException('Die Parzellennummer darf nicht leer sein.');
            }

            if (! \ctype_digit($value)) {
                throw new \InvalidArgumentException('Fehler: Die Parzellennummer darf ausschließlich aus Zahlen bestehen.');
            }
        }

        $intVal = (int) $value;

        // Erlaubt > 0 für normale Parzellen und 0 für DSGVO-Anonymisierung
        if ($intVal < 0) {
            throw new \InvalidArgumentException('Fehler: Die Parzellennummer darf nicht negativ sein.');
        }

        $this->value = $intVal;
    }

    /**
     * Gibt die Parzellennummer für UI-Zwecke mit Nullen aufgefüllt zurück (z.B. 0036).
     */
    public function getFormatted(): string
    {
        return \str_pad((string) $this->value, 4, '0', \STR_PAD_LEFT);
    }

    public function __toString(): string
    {
        return $this->getFormatted();
    }
}
